<?php

namespace Drupal\external_entities\StorageClient;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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
   * Loads raw data for one or more entities.
   *
   * @param array|null $ids
   *   An array of IDs, or NULL to load all entities.
   *
   * @return array
   *   An array of raw data arrays indexed by their IDs.
   */
  public function loadMultiple(array $ids = NULL);

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
