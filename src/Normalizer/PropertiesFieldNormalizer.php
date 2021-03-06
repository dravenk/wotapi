<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\wotapi\WotApiResource\ResourceIdentifier;
use Drupal\wotapi\WotApiResource\ResourceIdentifierInterface;
use Drupal\wotapi\WotApiResource\ResourceObject;
use Drupal\wotapi\Normalizer\Value\CacheableNormalization;
use Drupal\wotapi\Routing\Routes;
use Drupal\wotapi\Entity\PropertyType;

/**
 * Normalizer class specific for entity reference field objects.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 */
class PropertiesFieldNormalizer extends FieldNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = EntityReferenceFieldItemListInterface::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    assert($field instanceof EntityReferenceFieldItemListInterface);
    // Build the relationship object based on the Entity Reference and normalize
    // that object instead.
    $definition = $field->getFieldDefinition();
    $cardinality = $definition
      ->getFieldStorageDefinition()
      ->getCardinality();
    $resource_identifiers = array_filter(ResourceIdentifier::toResourceIdentifiers($field->filterEmptyItems()), function (ResourceIdentifierInterface $resource_identifier) {
      return !$resource_identifier->getResourceType()->isInternal();
    });
    $field_name = $field->getName();
    $resource_object = $context['resource_object'];
    $context['field_name'] = $field_name;
    $normalized_items = CacheableNormalization::aggregate($this->serializer->normalize($resource_identifiers, $format, $context));
    assert($context['resource_object'] instanceof ResourceObject);
    $link_cacheability = new CacheableMetadata();
    if (!is_null($resource_object)) {
      $links = array_map(function (Url $link) use ($link_cacheability) {
        $href = $link->setAbsolute()->toString(TRUE);
        $link_cacheability->addCacheableDependency($href);
        return ['href' => $href->getGeneratedUrl()];
      }, static::getPropertiesLinks($context['resource_object'], $field_name));
    }
    $data_normalization = $normalized_items->getNormalization();

    $normalization = $cardinality === 1 ? array_shift($data_normalization) : $data_normalization;

    // "@type": "BrightnessProperty",
    //      "type": "integer",
    //      "title": "Brightness",
    //      "description": "The level of light from 0-100",
    //      "minimum" : 0,
    //      "maximum" : 100,
    //      "readOnly": true,
    if ($field->getItemDefinition()->getSetting('target_type') == 'wotapi_property') {
      $normalization = [];
      foreach ($field->referencedEntities() as $referenced_entity) {
        $bundle = PropertyType::load($referenced_entity->bundle());
        if ($bundle) {
          $at_type = $bundle->getAtType();
          $title = $bundle->getTitle();
          $unit = $bundle->getUnit();
          $description = $bundle->getDescription();
          if ($at_type) {
            $normalization['@type'] = $at_type;
          }
          if ($title) {
            $normalization['title'] = $title;
          }
          if ($description) {
            $normalization['description'] = $description;
          }
          if ($unit) {
            $normalization['unit'] = $unit;
          };
        }
        foreach ($referenced_entity->getFields() as $referenced_entity_field) {
          $field_definition = $referenced_entity_field->getFieldDefinition();
          if ($referenced_entity->isReadOnly()) {
            $normalization['readOnly'] = TRUE;
          }
          if ($field_definition instanceof FieldConfig) {
            $field_definition_type = $field_definition->getType();
            $normalization['type'] = $field_definition_type;
            if ($field_definition_type == 'integer') {
              $min = $field_definition->getSetting('min');
              if (!is_null($min)) {
                $normalization['minimum'] = $min;
              }
              $max = $field_definition->getSetting('max');
              if (!is_null($max)) {
                $normalization['maximum'] = $max;
              }
            }
          }
        }
      }
    }

    if (!empty($links)) {
      $normalization['links'] = $links;
    }
    return (new CacheableNormalization($normalized_items, $normalization))->withCacheableDependency($link_cacheability);
  }

  /**
   * Gets the links for the relationship.
   *
   * @param \Drupal\wotapi\WotApiResource\ResourceObject $relationship_context
   *   The WOT:API resource object context of the relationship.
   * @param string $relationship_field_name
   *   The internal relationship field name.
   *
   * @return array
   *   The relationship's links.
   */
  public static function getPropertiesLinks(ResourceObject $relationship_context, $relationship_field_name) {
    $resource_type = $relationship_context->getResourceType();
    if ($resource_type->isInternal() || !$resource_type->isLocatable()) {
      return [];
    }
    $public_field_name = $resource_type->getPublicName($relationship_field_name);

    $links = [];
    if (static::hasNonInternalResourceType($resource_type->getRelatableResourceTypesByField($public_field_name))) {
      $related_route_name = Routes::getRouteName($resource_type, "$public_field_name.related");
      array_push($links, Url::fromRoute($related_route_name, ['entity' => $relationship_context->getId()]));
    }

    return $links;
  }

  /**
   * Determines if a given list of resource types contains a non-internal type.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType[] $resource_types
   *   The WOT:API resource types to evaluate.
   *
   * @return bool
   *   FALSE if every resource type is internal, TRUE otherwise.
   */
  protected static function hasNonInternalResourceType(array $resource_types) {
    foreach ($resource_types as $resource_type) {
      if (!$resource_type->isInternal()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
