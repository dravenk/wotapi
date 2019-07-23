<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\wotapi\Routing\Routes;
use Drupal\wotapi\WotApiResource\ResourceObject;
use Drupal\wotapi\Normalizer\Value\CacheableNormalization;
use Drupal\wotapi\Normalizer\Value\CacheableOmission;

/**
 * Converts the WOT:API module ResourceObject into a WOT:API array structure.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
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

    // The property returns a single value: {"temperature": 21}
    // See https://iot.mozilla.org/wot/#property-resource
    $properties = [];
    if ($resource_type->getEntityTypeId()=='wotapi_property'){
      $current_uri = \Drupal::request()->getUri();
      $property_name = explode("/properties/",$current_uri)[1];
      foreach ($fields as $field_name => $field) {
        if ($field->getFieldDefinition() instanceof FieldConfig){
          $properties[$property_name] = $this->serializeField($field, $context, $format);
        }
      }
      if (count($properties) == 1 ){
        return CacheableNormalization::aggregate($properties)->withCacheableDependency($object);
      }
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
      "@context" => CacheableNormalization::permanent("https://iot.mozilla.org/schemas/"),
      //TODO
//      'type' => CacheableNormalization::permanent($resource_type->getTypeName()),
      'id' => CacheableNormalization::permanent(\Drupal::request()->getUri()),
      'properties' => CacheableNormalization::aggregate(array_intersect_key($normalizer_values, array_flip($relationship_field_names)))->omitIfEmpty(),
    ];

    $attributes = array_diff_key($normalizer_values, array_flip($relationship_field_names));
    foreach ($attributes as $key => $value){
      $normalization[$key] = $value;
    };

    // @Todo ugly code.
    $relationship_normalization = array_intersect_key($normalizer_values, array_flip($relationship_field_names));
    $normalization =  $this->setReferenceFieldsNormalize($normalization,$relationship_normalization,$context);

    $obj = CacheableNormalization::aggregate($normalization)->withCacheableDependency($object);
    return $obj;
  }

  /**
   * {@inheritdoc}
   */
  protected function setReferenceFieldsNormalize($normalization ,$relationship_normalization, $context =[]){
    $links = [];

    $object = $context['resource_object'];
    $link['rel'] = $object->getResourceType()->getTypeName();
    $self_links = $object->getLinks();
    foreach ($self_links as $k => $v){
      $href = $v[0];
      $link['href'] = $href->getHref();
    }
    array_push($links, $link);

    foreach ($relationship_normalization as $key => $value){

//      $property_values =[];

      foreach ($value->getNormalization() as $k => $v){

        if ($k == 'links' && count($v) == 1){
          $link['rel'] = $key;
          $link['href'] = $v[0]['href'];
          array_push($links,$link);
        }
//        if (is_int($k)){
//          $property_values[$v['id']]= $v;
//        } else{
//          // $k = links => $v = {"href"="/"}
//          $property_values[$k] = $v;
//        }
      }

//      if (count($value->getNormalization())>0) {
//        $normalization[$key] = CacheableNormalization::permanent($property_values);
//      } else{
//        $normalization[$key] = $value;
//      }
    };

    if (count($links)>0){
     $normalization['links'] = CacheableNormalization::permanent($links);
    }

    return $normalization;
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
