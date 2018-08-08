<?php

namespace Drupal\external_entities\Entity\Query\External;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Query\ConditionInterface;

/**
 * The external entities storage entity query class.
 */
class Query extends QueryBase implements QueryInterface {

  /**
   * The parameters to send to the external entity storage client.
   *
   * @var array
   */
  protected $parameters = [];

  /**
   * An array of fields keyed by the field alias.
   *
   * Each entry correlates to the arguments of
   * \Drupal\Core\Database\Query\SelectInterface::addField(), so the first one
   * is the table alias, the second one the field and the last one optional the
   * field alias.
   *
   * @var array
   */
  protected $fields = [];

  /**
   * Array of strings added for the group by clause.
   *
   * Keyed by string to avoid duplicates.
   *
   * @var array
   */
  protected $groupBy = [];

  /**
   * Stores the entity type manager used by the query.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Storage client instance.
   *
   * @var \Drupal\external_entities\StorageClient\ExternalEntityStorageClientInterface
   */
  protected $storageClient;

  /**
   * Constructs a query object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   * @param array $namespaces
   *   List of potential namespaces of the classes belonging to this query.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, $conjunction, array $namespaces, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $conjunction, $namespaces);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implements \Drupal\Core\Entity\Query\QueryInterface::execute().
   */
  public function execute() {
    return $this
      ->prepare()
      ->compile()
      ->addSort()
      ->finish()
      ->result();
  }

  /**
   * Prepares the basic query with proper metadata/tags and base fields.
   *
   * @throws \Drupal\Core\Entity\Query\QueryException
   *   Thrown if the base table does not exists.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Returns the called object.
   */
  protected function prepare() {
    $this->checkConditions();
    return $this;
  }

  /**
   * Check if all conditions are valid.
   *
   * @param \Drupal\Core\Entity\Query\ConditionInterface $condition
   *   The conditions to check.
   *
   * @throws QueryException
   */
  protected function checkConditions(ConditionInterface $condition = NULL) {
    if (is_null($condition)) {
      $condition = $this->condition;
    }
    foreach ($condition->conditions() as $c) {
      if ($c['field'] instanceof ConditionInterface) {
        $this->checkConditions($c['field']);
      }
      elseif ($c['operator'] && !in_array($c['operator'], $this->supportedOperators())) {
        throw new QueryException("Operator {$c['operator']} is not supported by external entity queries.");
      }
    }
  }

  /**
   * Returns the supported condition operators.
   *
   * @return array
   *   The supported condition operators.
   */
  protected function supportedOperators() {
    return [
      '=',
      'IN',
      'CONTAINS',
    ];
  }

  /**
   * Compiles the conditions.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Returns the called object.
   */
  protected function compile() {
    $this->condition->compile($this);
    return $this;
  }

  /**
   * Adds the sort to the build query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Returns the called object.
   */
  protected function addSort() {
    // TODO.
    return $this;
  }

  /**
   * Finish the query by adding fields, GROUP BY and range.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Returns the called object.
   */
  protected function finish() {
    // TODO: Provide a count query.
    $this->initializePager();

    if ($this->range) {
      $this->setParameter('range', $this->range);
    }

    return $this;
  }

  /**
   * Executes the query and returns the result.
   *
   * @return int|array
   *   Returns the query result as entity IDs.
   */
  protected function result() {
    if ($this->count) {
      return $this->getStorageClient()->countQuery($this->parameters);
    }

    $query_results = $this->getStorageClient()->query($this->parameters);
    $result = [];
    foreach ($query_results as $query_result) {
      $id = $query_result[$this->getExternalEntityType()->getFieldMapping('id', 'value')];
      $result[$id] = $id;
    }

    return $result;
  }

  /**
   * Get the storage client for a bundle.
   *
   * @return \Drupal\external_entities\StorageClient\ExternalEntityStorageClientInterface
   *   The external entity storage client.
   */
  protected function getStorageClient() {
    if (!$this->storageClient) {
      $this->storageClient = $this->getExternalEntityType()->getStorageClient();

    }
    return $this->storageClient;
  }

  /**
   * Determines whether the query requires GROUP BY and ORDER BY MIN/MAX.
   *
   * @return bool
   *   TRUE if required, FALSE otherwise.
   */
  protected function isSimpleQuery() {
    return (!$this->pager && !$this->range && !$this->count);
  }

  /**
   * Implements the magic __clone method.
   *
   * Reset fields and GROUP BY when cloning.
   */
  public function __clone() {
    parent::__clone();
    $this->fields = [];
    $this->groupBy = [];
  }

  /**
   * Set a parameter.
   *
   * @param string $key
   *   The parameter key.
   * @param mixed $value
   *   The parameter value.
   */
  public function setParameter($key, $value) {
    if ($key !== $this->entityType->getKey('bundle')) {
      $this->parameters[$key] = $value;
    }
  }

  /**
   * Gets the external entity type.
   *
   * @return \Drupal\external_entities\ExternalEntityTypeInterface
   *   The external entity type.
   */
  public function getExternalEntityType() {
    return $this
      ->entityTypeManager
      ->getStorage('external_entity_type')
      ->load($this->getEntityTypeId());
  }

}
