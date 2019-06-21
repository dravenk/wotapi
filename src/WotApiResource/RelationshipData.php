<?php

namespace Drupal\wotapi\WotApiResource;

use Drupal\Component\Assertion\Inspector;

/**
 * Represents the data of a relationship object or relationship document.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 */
class RelationshipData extends Data {

  /**
   * RelationshipData constructor.
   *
   * @param \Drupal\wotapi\WotApiResource\ResourceIdentifier[] $data
   *   Resource objects that are the primary data for the response.
   * @param int $cardinality
   *   The number of ResourceIdentifiers that this collection may contain.
   *
   * @see \Drupal\wotapi\WotApiResource\Data::__construct
   */
  public function __construct(array $data, $cardinality = -1) {
    assert(Inspector::assertAllObjects($data, ResourceIdentifier::class));
    parent::__construct($data, $cardinality);
  }

}
