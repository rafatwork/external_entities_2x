<?php

namespace Drupal\external_entities;

use Drupal\Core\Entity\ContentEntityStorageBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\external_entities\Entity\ExternalEntity;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Defines the storage handler class for external entities.
 *
 * This extends the base storage class, adding required special handling for
 * e entities.
 */
class ExternalEntityStorage extends ContentEntityStorageBase implements ExternalEntityStorageInterface {

  /**
   * The external storage client manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $storageClientManager;

  /**
   * Storage client instance.
   *
   * @var \Drupal\external_entities\StorageClient\ExternalEntityStorageClientInterface
   */
  protected $storageClient;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager'),
      $container->get('cache.entity'),
      $container->get('plugin.manager.external_entities.storage_client'),
      $container->get('datetime.time')
    );
  }

  /**
   * Constructs a new ExternalEntityStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to be used.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $storage_client_manager
   *   The storage client manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityManagerInterface $entity_manager, CacheBackendInterface $cache, PluginManagerInterface $storage_client_manager, TimeInterface $time) {
    parent::__construct($entity_type, $entity_manager, $cache);
    $this->storageClientManager = $storage_client_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClient() {
    if (!$this->storageClient) {
      $this->storageClient = $this
        ->getEntityType()
        ->getStorageClient();
    }
    return $this->storageClient;
  }

  /**
   * Acts on entities before they are deleted and before hooks are invoked.
   *
   * Used before the entities are deleted and before invoking the delete hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entities.
   *
   * @throws EntityStorageException
   */
  public function preDelete(array $entities) {
    if ($this->getExternalEntityType()->isReadOnly()) {
      throw new EntityStorageException($this->t('Can not delete read-only external entities.'));
    }
  }

  /**
   * Gets the entity type definition.
   *
   * @return \Drupal\external_entities\ExternalEntityTypeInterface
   *   Entity type definition.
   */
  public function getEntityType() {
    /* @var \Drupal\external_entities\ExternalEntityTypeInterface $entity_type */
    $entity_type = $this->entityType;
    return $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    // Do the actual delete.
    foreach ($entities as $entity) {
      $this->getStorageClient()->delete($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    // Attempt to load entities from the persistent cache. This will remove IDs
    // that were loaded from $ids.
    $entities_from_cache = $this->getFromPersistentCache($ids);

    // Load any remaining entities from the external storage.
    if ($entities_from_storage = $this->getFromExternalStorage($ids)) {
      $this->invokeStorageLoadHook($entities_from_storage);
      $this->setPersistentCache($entities_from_storage);
    }

    $entities = $entities_from_cache + $entities_from_storage;

    // Map annotation fields to annotatable external entities.
    foreach ($entities as $external_entity) {
      /* @var \Drupal\external_entities\ExternalEntityInterface $external_entity */
      if ($external_entity->getExternalEntityType()->isAnnotatable()) {
        $external_entity->mapAnnotationFields();
      }
    }

    return $entities;
  }

  /**
   * Gets entities from the external storage.
   *
   * @param array|null $ids
   *   If not empty, return entities that match these IDs. Return no entities
   *   when NULL.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Array of entities from the storage.
   */
  protected function getFromExternalStorage(array $ids) {
    $entities = [];

    if (!empty($ids)) {
      // Sanitize IDs. Before feeding ID array into buildQuery, check whether
      // it is empty as this would load all entities.
      $ids = $this->cleanIds($ids);
    }

    if ($ids) {
      foreach ($ids as $id) {
        $raw_data = $this
          ->getExternalEntityType()
          ->getStorageClient()
          ->load($id);

        if (!empty($raw_data)) {
          /* @var \Drupal\external_entities\ExternalEntityInterface $external_entity */
          $external_entity = $this->create();
          $entities[$id] = $external_entity
            ->mapRawData($raw_data)
            ->enforceIsNew(FALSE);
        }
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function setPersistentCache($entities) {
    if (!$this->entityType->isPersistentlyCacheable()) {
      return;
    }

    $cache_tags = [
      $this->entityTypeId . '_values',
      'entity_field_info',
    ];

    foreach ($entities as $id => $entity) {
      $max_age = $this->getExternalEntityType()->getPersistentCacheMaxAge();
      $expire = $max_age === Cache::PERMANENT
        ? Cache::PERMANENT
        : $this->time->getRequestTime() + $max_age;
      $this->cacheBackend->set($this->buildCacheId($id), $entity, $expire, $cache_tags);
    }
  }

  /**
   * Acts on an entity before the presave hook is invoked.
   *
   * Used before the entity is saved and before invoking the presave hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @throws EntityStorageException
   */
  public function preSave(EntityInterface $entity) {
    $external_entity_type = $this->getExternalEntityType();
    if ($external_entity_type->isReadOnly() && !$external_entity_type->isAnnotatable()) {
      throw new EntityStorageException($this->t('Can not save read-only external entities.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /* @var \Drupal\external_entities\ExternalEntityInterface $entity */
    $result = FALSE;

    $external_entity_type = $this->getExternalEntityType();
    if (!$external_entity_type->isReadOnly()) {
      $result = $this->getStorageClient()->save($entity);
    }

    if ($external_entity_type->isAnnotatable()) {
      $referenced_entities = $entity
        ->get(ExternalEntity::ANNOTATION_FIELD)
        ->referencedEntities();
      if ($referenced_entities) {
        $annotation = array_shift($referenced_entities);
        $annotation->set($external_entity_type->getAnnotationFieldName(), $entity->id());
        $annotation->save();
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'entity.query.external';
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    return !$entity->isNew();
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteFieldItems($entities) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadRevisionFieldItems($revision_id) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
  }

  /**
   * {@inheritdoc}
   */
  protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, $batch_size) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition) {
  }

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    return $as_bool ? 0 : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasData() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalEntityType() {
    return $this->entityManager
      ->getStorage('external_entity_type')
      ->load($this->getEntityTypeId());
  }

}
