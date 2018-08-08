<?php

namespace Drupal\external_entities;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Plugin\views\filter\Access;

/**
 * Defines a generic access control handler for external entities.
 */
class ExternalEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /* @var \Drupal\external_entities\ExternalEntityInterface $entity */
    $result = parent::checkAccess($entity, $operation, $account);

    if ($result->isNeutral()) {
      if (!in_array($operation, ['view label', 'view']) && $entity->getExternalEntityType()->isReadOnly()) {
        $result = AccessResult::forbidden()->addCacheableDependency($entity);
      }
      else {
        $result = AccessResult::allowedIfHasPermission($account, "{$operation} {$entity->getEntityTypeId()} external entity");
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);

    /* @var \Drupal\external_entities\ExternalEntityTypeInterface $external_entity_type */
    $external_entity_type = \Drupal::entityTypeManager()
      ->getStorage('external_entity_type')
      ->load($this->entityTypeId);
    if ($external_entity_type && $external_entity_type->isReadOnly()) {
      $result = AccessResult::forbidden()->addCacheableDependency($this->entityType);
    }

    return $result;
  }

}
