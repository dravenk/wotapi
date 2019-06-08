<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\wotapi\WotApiResource\ResourceObject;
use Drupal\wotapi\Normalizer\Value\CacheableNormalization;
use Drupal\wotapi\Normalizer\Value\CacheableOmission;

/**
 * Converts the WOT:API module ResourceObject into a WOT:API array structure.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 */
class ResourceObjectNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = ResourceObject::class;

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof ResourceObject);
    // If the fields to use were specified, only output those field values.
    $context['resource_object'] = $object;
    $resource_type = $object->getResourceType();
    $resource_type_name = $resource_type->getTypeName();
    $fields = $object->getFields();
    // Get the bundle ID of the requested resource. This is used to determine if
    // this is a bundle level resource or an entity level resource.
    if (!empty($context['sparse_fieldset'][$resource_type_name])) {
      $field_names = $context['sparse_fieldset'][$resource_type_name];
    }
    else {
      $field_names = array_keys($fields);
    }
    $normalizer_values = [];
    foreach ($fields as $field_name => $field) {
      $in_sparse_fieldset = in_array($field_name, $field_names);
      // Omit fields not listed in sparse fieldsets.
      if (!$in_sparse_fieldset) {
        continue;
      }
      $normalizer_values[$field_name] = $this->serializeField($field, $context, $format);
    }
    $relationship_field_names = array_keys($resource_type->getRelatableResourceTypes());
//    return CacheableNormalization::aggregate([
//      'type' => CacheableNormalization::permanent($resource_type->getTypeName()),
//      'id' => CacheableNormalization::permanent($object->getId()),
//      'attributes' => CacheableNormalization::aggregate(array_diff_key($normalizer_values, array_flip($relationship_field_names)))->omitIfEmpty(),
//      'properties' => CacheableNormalization::aggregate(array_intersect_key($normalizer_values, array_flip($relationship_field_names)))->omitIfEmpty(),
//      'links' => $this->serializer->normalize($object->getLinks(), $format, $context)->omitIfEmpty(),
//    ])->withCacheableDependency($object);

    $normalization = [
      'type' => CacheableNormalization::permanent($resource_type->getTypeName()),
      'id' => CacheableNormalization::permanent($object->getId()),
      'properties' => CacheableNormalization::aggregate(array_intersect_key($normalizer_values, array_flip($relationship_field_names)))->omitIfEmpty(),
      'links' => $this->serializer->normalize($object->getLinks(), $format, $context)->omitIfEmpty(),
    ];

    $attr = array_diff_key($normalizer_values, array_flip($relationship_field_names));
    foreach ($attr as $key => $value){
      $normalization[$key] = $value;
    };

    $obj = CacheableNormalization::aggregate($normalization)->withCacheableDependency($object);
    return $obj;
  }

  /**
   * Serializes a given field.
   *
   * @param mixed $field
   *   The field to serialize.
   * @param array $context
   *   The normalization context.
   * @param string $format
   *   The serialization format.
   *
   * @return \Drupal\wotapi\Normalizer\Value\CacheableNormalization
   *   The normalized value.
   */
  protected function serializeField($field, array $context, $format) {
    // Only content entities contain FieldItemListInterface fields. Since config
    // entities do not have "real" fields and therefore do not have field access
    // restrictions.
    if ($field instanceof FieldItemListInterface) {
      $field_access_result = $field->access('view', $context['account'], TRUE);
      if (!$field_access_result->isAllowed()) {
        return new CacheableOmission(CacheableMetadata::createFromObject($field_access_result));
      }
      $normalized_field = $this->serializer->normalize($field, $format, $context);
      assert($normalized_field instanceof CacheableNormalization);
      return $normalized_field->withCacheableDependency(CacheableMetadata::createFromObject($field_access_result));
    }
    else {
      // @todo Replace this workaround after https://www.drupal.org/node/3043245
      //   or remove the need for this in https://www.drupal.org/node/2942975.
      //   See \Drupal\layout_builder\Normalizer\LayoutEntityDisplayNormalizer.
      if ($context['resource_object']->getResourceType()->getDeserializationTargetClass() === 'Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay' && $context['resource_object']->getField('third_party_settings') === $field) {
        unset($field['layout_builder']['sections']);
      }

      // Config "fields" in this case are arrays or primitives and do not need
      // to be normalized.
      return CacheableNormalization::permanent($field);
    }
  }

}
