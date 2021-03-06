<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\wotapi\Normalizer\Value\CacheableNormalization;
use Drupal\serialization\Normalizer\NormalizerBase as SerializationNormalizerBase;

/**
 * Base normalizer used in all WOT:API normalizers.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 */
abstract class NormalizerBase extends SerializationNormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $format = 'api_json';

  /**
   * Rasterizes a value recursively.
   *
   * This is mainly for configuration entities where a field can be a tree of
   * values to rasterize.
   *
   * @param mixed $value
   *   Either a scalar, an array or a rasterizable object.
   *
   * @return mixed
   *   The rasterized value.
   */
  protected static function rasterizeValueRecursive($value) {
    if (!$value || is_scalar($value)) {
      return $value;
    }
    if (is_array($value)) {
      $output = [];
      foreach ($value as $key => $item) {
        $output[$key] = static::rasterizeValueRecursive($item);
      }

      return $output;
    }
    if ($value instanceof CacheableNormalization) {
      return $value->getNormalization();
    }
    // If the object can be turned into a string it's better than nothing.
    if (method_exists($value, '__toString')) {
      return $value->__toString();
    }

    // We give up, since we do not know how to rasterize this.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFormat($format = NULL) {
    // The parent implementation allows format-specific normalizers to be used
    // for formatless normalization. The WOT:API module wants to be cautious.
    // Hence it only allows its normalizers to be used for the WOT:API format,
    // to avoid WOT:API-specific normalizations showing up in the REST API.
    return $format === $this->format;
  }

}
