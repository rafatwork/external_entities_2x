<?php

namespace Drupal\external_entities\StorageClient;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\external_entities\ExternalEntityTypeInterface;
use Drupal\external_entities\ExternalEntityInterface;

/**
 * Defines an interface for external entity storage client plugins.
 */
interface ExternalEntityStorageClientInterface extends PluginInspectionInterface, ConfigurablePluginInterface, ContainerFactoryPluginInterface {

  /**
   * Return the name of the external entity storage client.
   *
   * @return string
   *   The name of the external entity storage client.
   */
  public function getName();

  /**
   * Retrieves the external entity type entity for this storage client.
   *
   * @return \Drupal\external_entities\ExternalEntityTypeInterface
   *   The external entity type entity.
   */
  public function getExternalEntityType();

  /**
   * Sets the external entity type entity for this storage client.
   *
   * @param \Drupal\external_entities\ExternalEntityTypeInterface $external_entity_type
   *   The external entity type entity.
   *
   * @return $this
   */
  public function setExternalEntityType(ExternalEntityTypeInterface $external_entity_type);

  /**
   * Loads one entity.
   *
   * @param mixed $id
   *   The ID of the entity to load.
   *
   * @return array|null
   *   A raw data array, NULL if no data returned.
   */
  public function load($id);

  /**
   * Saves the entity permanently.
   *
   * @param \Drupal\external_entities\ExternalEntityInterface $entity
   *   The entity to save.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   */
  public function save(ExternalEntityInterface $entity);

  /**
   * Deletes permanently saved entities.
   *
   * @param \Drupal\external_entities\ExternalEntityInterface $entity
   *   The external entity object to delete.
   */
  public function delete(ExternalEntityInterface $entity);

  /**
   * Query the external entities.
   *
   * @param array $parameters
   *   Key-value pairs of fields to query.
   */
  public function query(array $parameters);

  /**
   * Query the external entities and return the match count.
   *
   * @param array $parameters
   *   Key-value pairs of fields to query.
   *
   * @return int
   *   A count of matched external entities.
   */
  public function countQuery(array $parameters);

}
