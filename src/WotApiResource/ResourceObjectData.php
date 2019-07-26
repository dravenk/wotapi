<?php

namespace Drupal\wotapi\WotApiResource;

use Drupal\Component\Assertion\Inspector;
use Drupal\wotapi\Exception\EntityAccessDeniedHttpException;

/**
 * Represents the primary data for individual and collection documents.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 */
class ResourceObjectData extends Data {

  /**
   * ResourceObjectData constructor.
   *
   * @param \Drupal\wotapi\WotApiResource\ResourceObject[]|\Drupal\wotapi\Exception\EntityAccessDeniedHttpException[] $data
   *   Resource objects that are the primary data for the response.
   * @param int $cardinality
   *   The number of resources that this collection may contain.
   *
   * @see \Drupal\wotapi\WotApiResource\Data::__construct
   */
  public function __construct($data, $cardinality = -1) {
    assert(Inspector::assertAllObjects($data, ResourceObject::class, EntityAccessDeniedHttpException::class));
    parent::__construct($data, $cardinality);
  }

  /**
   * Gets only data to be exposed.
   *
   * @return static
   */
  public function getAccessible() {
    $accessible_data = [];
    foreach ($this->data as $resource_object) {
      if (!$resource_object instanceof EntityAccessDeniedHttpException) {
        $accessible_data[] = $resource_object;
      }
    }
    return new static($accessible_data, $this->cardinality);
  }

}
