<?php

namespace Drupal\wotapi\Encoder;

use Drupal\serialization\Encoder\JsonEncoder as SerializationJsonEncoder;

/**
 * Encodes WOT:API data.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 */
class JsonEncoder extends SerializationJsonEncoder {

  /**
   * The formats that this Encoder supports.
   *
   * @var string
   */
  protected static $format = ['api_json'];

}
