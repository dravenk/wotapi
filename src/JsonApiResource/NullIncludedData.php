<?php

namespace Drupal\wotapi\WotApiResource;

/**
 * Use when there are no included resources but a Data object is required.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 */
class NullIncludedData extends IncludedData {

  /**
   * NullData constructor.
   */
  public function __construct() {
    parent::__construct([]);
  }

}
