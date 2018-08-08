<?php

namespace Drupal\external_entities;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a common interface for all external entity objects.
 */
interface ExternalEntityInterface extends ContentEntityInterface {

  /**
   * Gets the external entity type.
   *
   * @return \Drupal\external_entities\ExternalEntityTypeInterface
   *   The external entity type.
   */
  public function getExternalEntityType();

  /**
   * Extract raw data from this entity.
   *
   * @return array
   *   The raw data array.
   */
  public function extractRawData();

  /**
   * Map raw data to this entity.
   *
   * @param array $raw_data
   *   An associative array with raw data.
   *
   * @return $this
   */
  public function mapRawData(array $raw_data);

  /**
   * Gets the associated annotation entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The annotation entity, null otherwise.
   */
  public function getAnnotation();

  /**
   * Map the annotations entity fields to this entity.
   *
   * @return $this
   */
  public function mapAnnotationFields();

}
