<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\wotapi\Normalizer\Value\CacheableNormalization;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Converts the Drupal field structure to a WOT:API array structure.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 */
class FieldNormalizer extends NormalizerBase implements DenormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = FieldItemListInterface::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    /* @var \Drupal\Core\Field\FieldItemListInterface $field */
    $normalized_items = $this->normalizeFieldItems($field, $format, $context);

    $cardinality = $field->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getCardinality();

    return $cardinality === 1
      ? array_shift($normalized_items) ?: CacheableNormalization::permanent(NULL)
      : CacheableNormalization::aggregate($normalized_items);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {}

  /**
   * Helper function to normalize field items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field object.
   * @param string $format
   *   The format.
   * @param array $context
   *   The context array.
   *
   * @return \Drupal\wotapi\Normalizer\Value\FieldItemNormalizerValue[]
   *   The array of normalized field items.
   */
  protected function normalizeFieldItems(FieldItemListInterface $field, $format, array $context) {
    $normalizer_items = [];
    if (!$field->isEmpty()) {
      foreach ($field as $field_item) {
        $normalizer_items[] = $this->serializer->normalize($field_item, $format, $context);
      }
    }
    return $normalizer_items;
  }

}
