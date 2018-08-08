<?php

namespace Drupal\external_entities;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines a generic access control handler for external entities.
 */
class ExternalEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * Allow access to the external entity label.
   *
   * @var bool
   */
  protected $viewLabelOperation = TRUE;

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /* @var \Drupal\external_entities\ExternalEntityInterface $entity */
    if (!in_array($operation, ['view label', 'view']) && $entity->getExternalEntityType()->isReadOnly()) {
      return $return_as_object
        ? AccessResult::forbidden()->cachePerPermissions()
        : FALSE;
    }

    return parent::access($entity, $operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {
    /* @var \Drupal\external_entities\ExternalEntityTypeInterface $external_entity_type */
    $external_entity_type = \Drupal::entityTypeManager()
      ->getStorage('external_entity_type')
      ->load($this->entityTypeId);
    if ($external_entity_type && $external_entity_type->isReadOnly()) {
      return $return_as_object
        ? AccessResult::forbidden()->cachePerPermissions()
        : FALSE;
    }

    return parent::createAccess($entity_bundle, $account, $context, $return_as_object);
  }

}
