<?php

namespace Drupal\external_entities\StorageClient;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\external_entities\ExternalEntityTypeInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface;

/**
 * Base class for external entity storage clients.
 */
abstract class ExternalEntityStorageClientBase extends PluginBase implements ExternalEntityStorageClientInterface {

  // Normally, we'd just need \Drupal\Core\Entity\DependencyTrait here for
  // plugins. However, in a few cases, plugins use plugins themselves, and then
  // the additional calculatePluginDependencies() method from this trait is
  // useful. Since PHP 5 complains when adding this trait along with its
  // "parent" trait to the same class, we just add it here in case a child class
  // does need it.
  use PluginDependencyTrait;

  /**
   * The external entity type this storage client is configured for.
   *
   * @var \Drupal\external_entities\ExternalEntityTypeInterface
   */
  protected $externalEntityType;

  /**
   * The response decoder factory.
   *
   * @var \Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface
   */
  protected $responseDecoderFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    if (!empty($configuration['_external_entity_type']) && $configuration['_external_entity_type'] instanceof ExternalEntityTypeInterface) {
      $this->setExternalEntityType($configuration['_external_entity_type']);
      unset($configuration['_external_entity_type']);
    }

    $configuration += $this->defaultConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $storage_client = new static($configuration, $plugin_id, $plugin_definition);
    $storage_client->setStringTranslation($container->get('string_translation'));
    $storage_client->setResponseDecoderFactory($container->get('external_entities.response_decoder_factory'));
    return $storage_client;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $plugin_definition = $this->getPluginDefinition();
    return isset($plugin_definition['description']) ? $plugin_definition['description'] : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalEntityType() {
    return $this->externalEntityType;
  }

  /**
   * {@inheritdoc}
   */
  public function setExternalEntityType(ExternalEntityTypeInterface $external_entity_type) {
    $this->externalEntityType = $external_entity_type;
    return $this;
  }

  /**
   * Returns the response decoder factory.
   *
   * @return \Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface
   *   The response decoder factory.
   */
  public function getResponseDecoderFactory() {
    return $this->responseDecoderFactory ?: \Drupal::service('external_entities.response_decoder_factory');
  }

  /**
   * Sets the response decoder factory.
   *
   * @param \Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface $response_decoder_factory
   *   A response decoder factory.
   *
   * @return $this
   */
  public function setResponseDecoderFactory(ResponseDecoderFactoryInterface $response_decoder_factory) {
    $this->responseDecoderFactory = $response_decoder_factory;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($configuration, $this->defaultConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function countQuery(array $parameters) {
    return count($this->query($parameters));
  }

}
