<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\wotapi\WotApiResource\Data;
use Drupal\wotapi\Normalizer\Value\CacheableNormalization;

/**
 * Normalizes WOT:API Data objects.
 *
 * @internal
 */
class DataNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = Data::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof Data);
    $cacheable_normalizations = array_map(function ($resource) use ($format, $context) {
      return $this->serializer->normalize($resource, $format, $context);
    }, $object->toArray());
    return $object->getCardinality() === 1
      ? array_shift($cacheable_normalizations) ?: CacheableNormalization::permanent(NULL)
      : CacheableNormalization::aggregate($cacheable_normalizations);
  }

}
