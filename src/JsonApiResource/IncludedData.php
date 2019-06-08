<?php

namespace Drupal\wotapi\WotApiResource;

use Drupal\Component\Assertion\Inspector;
use Drupal\wotapi\Exception\EntityAccessDeniedHttpException;

/**
 * Represents the included member of a WOT:API document.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 */
class IncludedData extends ResourceObjectData {

  /**
   * IncludedData constructor.
   *
   * @param \Drupal\wotapi\WotApiResource\ResourceObject[]|\Drupal\wotapi\Exception\EntityAccessDeniedHttpException[] $data
   *   Resource objects that are the primary data for the response.
   *
   * @see \Drupal\wotapi\WotApiResource\Data::__construct
   */
  public function __construct($data) {
    assert(Inspector::assertAllObjects($data, ResourceObject::class, EntityAccessDeniedHttpException::class));
    parent::__construct($data, -1);
  }

}
