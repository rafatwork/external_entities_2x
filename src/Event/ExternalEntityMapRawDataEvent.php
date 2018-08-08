<?php

namespace Drupal\external_entities\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\external_entities\ExternalEntityInterface;

/**
 * Defines an external entity raw data mapping event.
 */
class ExternalEntityMapRawDataEvent extends Event {

  /**
   * The external entity.
   *
   * @var \Drupal\external_entities\ExternalEntityInterface
   */
  protected $entity;

  /**
   * The raw data.
   *
   * @var array
   */
  protected $rawData;

  /**
   * Constructs a map raw data event object.
   *
   * @param \Drupal\external_entities\ExternalEntityInterface $entity
   *   The external entity.
   * @param array $raw_data
   *   The raw data being mapped.
   */
  public function __construct(ExternalEntityInterface $entity, array $raw_data) {
    $this->entity = $entity;
    $this->rawData = $raw_data;
  }

  /**
   * Gets the external entity.
   *
   * @return \Drupal\external_entities\ExternalEntityInterface
   *   The external entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Gets the raw data being mapped.
   *
   * @return array
   *   The raw data.
   */
  public function getRawData() {
    return $this->rawData;
  }

}
