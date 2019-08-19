<?php

namespace Drupal\wotapi\WotApiResource;

use Drupal\Component\Assertion\Inspector;
use Drupal\wotapi\Exception\EntityAccessDeniedHttpException;

/**
 * Represents resource data that should be omitted from the WOT:API document.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 */
class OmittedData extends ResourceObjectData {

  /**
   * OmittedData constructor.
   *
   * @param \Drupal\wotapi\Exception\EntityAccessDeniedHttpException[] $data
   *   Resource objects that are the primary data for the response.
   *
   * @see \Drupal\wotapi\WotApiResource\Data::__construct
   */
  public function __construct(array $data) {
    assert(Inspector::assertAllObjects($data, EntityAccessDeniedHttpException::class));
    parent::__construct($data, -1);
  }

}
