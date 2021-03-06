<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\StorageClient;

/**
 * External entities storage client based on a REST API.
 *
 * @ExternalEntityStorageClient(
 *   id = "wiki",
 *   label = @Translation("Wiki"),
 *   description = @Translation("Retrieves external entities from a Wikipedia API.")
 * )
 */
class Wiki extends Rest {

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    $response = $this->httpClient->request(
      'GET',
      $this->configuration['endpoint'],
      [
        'headers' => $this->getHttpHeaders(),
        'query' => $this->getSingleQueryParameters($id),
      ]
    );

    $result = $this
      ->getResponseDecoderFactory()
      ->getDecoder($this->configuration['response_format'])
      ->decode($response->getBody());

    if (!empty($result['query']['pages'][$id])) {
      return $result['query']['pages'][$id];
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function query(array $parameters) {
    $response = $this->httpClient->request(
      'GET',
      $this->configuration['endpoint'],
      [
        'headers' => $this->getHttpHeaders(),
        'query' => $this->getListQueryParameters($parameters),
      ]
    );

    $results = $this
      ->getResponseDecoderFactory()
      ->getDecoder($this->configuration['response_format'])
      ->decode($response->getBody());

    $items = [];
    if (!empty($results['query']['categorymembers'])) {
      $items = array_values($results['query']['categorymembers']);
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function getSingleQueryParameters($id, array $parameters = []) {
    return parent::getSingleQueryParameters($id, [
      'pageids' => $id,
    ]);
  }

}
