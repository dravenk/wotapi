<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Url;
use Drupal\wotapi\WotApiResource\ResourceIdentifier;
use Drupal\wotapi\WotApiResource\ResourceIdentifierInterface;
use Drupal\wotapi\WotApiResource\ResourceObject;
use Drupal\wotapi\WotApiSpec;
use Drupal\wotapi\Normalizer\Value\CacheableNormalization;
use Drupal\wotapi\Routing\Routes;

/**
 * Normalizer class specific for entity reference field objects.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
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
    $context['field_name'] = $field->getName();
    $normalized_items = CacheableNormalization::aggregate($this->serializer->normalize($resource_identifiers, $format, $context));
    assert($context['resource_object'] instanceof ResourceObject);
    $link_cacheability = new CacheableMetadata();
    $links = array_map(function (Url $link) use ($link_cacheability) {
      $href = $link->setAbsolute()->toString(TRUE);
      $link_cacheability->addCacheableDependency($href);
      return ['href' => $href->getGeneratedUrl()];
    }, static::getPropertiesLinks($context['resource_object'], $field->getName()));
    $data_normalization = $normalized_items->getNormalization();
    $normalization = [
      // Empty 'to-one' properties must be NULL.
      // Empty 'to-many' properties must be an empty array.
      // @link http://wotapi.org/format/#document-resource-object-linkage
      'data' => $cardinality === 1 ? array_shift($data_normalization) : $data_normalization,
    ];
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
//    $relationship_route_name = Routes::getRouteName($resource_type, "$public_field_name.relationship.get");

    $links = [];
//    $links['self'] = Url::fromRoute($relationship_route_name, ['entity' => $relationship_context->getId()]);

    if (static::hasNonInternalResourceType($resource_type->getRelatableResourceTypesByField($public_field_name))) {
      $related_route_name = Routes::getRouteName($resource_type, "$public_field_name.related");
//      $links['related'] = Url::fromRoute($related_route_name, ['entity' => $relationship_context->getId()]);
      array_push($links, Url::fromRoute($related_route_name, ['entity' => $relationship_context->getId()]));
    }
//    if ($resource_type->isVersionable()) {
//      $version_query_parameter = [WotApiSpec::VERSION_QUERY_PARAMETER => $relationship_context->getVersionIdentifier()];
//      $links['self']->setOption('query', $version_query_parameter);
//      if (isset($links['related'])) {
//        $links['related']->setOption('query', $version_query_parameter);
//      }
//    }

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
