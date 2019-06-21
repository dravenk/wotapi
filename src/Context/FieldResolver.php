<?php

namespace Drupal\wotapi\Context;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\wotapi\ResourceType\ResourceType;
use Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;

/**
 * A service that evaluates external path expressions against Drupal fields.
 *
 * This class performs 3 essential functions, path resolution, path validation
 * and path expansion.
 *
 * Path resolution:
 * Path resolution refers to the ability to map a set of external field names to
 * their internal counterparts. This is necessary because a resource type can
 * provide aliases for its field names. For example, the resource type @code
 * node--article @endcode might "alias" the internal field name @code
 * uid @endcode to the external field name @code author @endcode. This permits
 * an API consumer to request @code
 * /wotapi/node/article?include=author @endcode for a better developer
 * experience.
 *
 * Path validation:
 * Path validation refers to the ability to ensure that a requested path
 * corresponds to a valid set of internal fields. For example, if an API
 * consumer may send a @code GET @endcode request to @code
 * /wotapi/node/article?sort=author.field_first_name @endcode. The field
 * resolver ensures that @code uid @endcode (which would have been resolved
 * from @code author @endcode) exists on article nodes and that @code
 * field_first_name @endcode exists on user entities. However, in the case of
 * an @code include @endcode path, the field resolver would raise a client error
 * because @code field_first_name @endcode is not an entity reference field,
 * meaning it does not identify any related resources that can be included in a
 * compound document.
 *
 * Path expansion:
 * Path expansion refers to the ability to expand a path to an entity query
 * compatible field expression. For example, a request URL might have a query
 * string like @code ?filter[field_tags.name]=aviation @endcode, before
 * constructing the appropriate entity query, the entity query system needs the
 * path expression to be "expanded" into @code field_tags.entity.name @endcode.
 * In some rare cases, the entity query system needs this to be expanded to
 * @code field_tags.entity:taxonomy_term.name @endcode; the field resolver
 * simply does this by default for every path.
 *
 * *Note:* path expansion is *not* performed for @code include @endcode paths.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 */
class FieldResolver {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The entity type bundle information service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The WOT:API resource type repository service.
   *
   * @var \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Creates a FieldResolver instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The bundle info service.
   * @param \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, ResourceTypeRepositoryInterface $resource_type_repository, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldManager = $field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Expands the internal path with the "entity" keyword.
   *
   * @param string[] $references
   *   The resolved internal field names of all entity references.
   * @param string[] $property_path
   *   (optional) A sub-property path for the last field in the path.
   *
   * @return string
   *   The expanded and imploded path.
   */
  protected function constructInternalPath(array $references, array $property_path = []) {
    // Reconstruct the path parts that are referencing sub-properties.
    $field_path = implode('.', $property_path);

    // This rebuilds the path from the real, internal field names that have
    // been traversed so far. It joins them with the "entity" keyword as
    // required by the entity query system.
    $entity_path = implode('.', $references);

    // Reconstruct the full path to the final reference field.
    return (empty($field_path)) ? $entity_path : $entity_path . '.' . $field_path;
  }

  /**
   * Get all item definitions from a set of resources types by a field name.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType[] $resource_types
   *   The resource types on which the field might exist.
   * @param string $field_name
   *   The field for which to retrieve field item definitions.
   *
   * @return \Drupal\Core\TypedData\ComplexDataDefinitionInterface[]
   *   The found field item definitions.
   */
  protected function getFieldItemDefinitions(array $resource_types, $field_name) {
    return array_reduce($resource_types, function ($result, ResourceType $resource_type) use ($field_name) {
      /* @var \Drupal\wotapi\ResourceType\ResourceType $resource_type */
      $entity_type = $resource_type->getEntityTypeId();
      $bundle = $resource_type->getBundle();
      $definitions = $this->fieldManager->getFieldDefinitions($entity_type, $bundle);
      if (isset($definitions[$field_name])) {
        $result[$resource_type->getTypeName()] = $definitions[$field_name]->getItemDefinition();
      }
      return $result;
    }, []);
  }

  /**
   * Resolves the UUID field name for a resource type.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The resource type for which to get the UUID field name.
   *
   * @return string
   *   The resolved internal name.
   */
  protected function getIdFieldName(ResourceType $resource_type) {
    $entity_type = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId());
    return $entity_type->getKey('uuid');
  }

  /**
   * Resolves the internal field name based on a collection of resource types.
   *
   * @param string $field_name
   *   The external field name.
   * @param \Drupal\wotapi\ResourceType\ResourceType[] $resource_types
   *   The resource types from which to get an internal name.
   *
   * @return string
   *   The resolved internal name.
   */
  protected function getInternalName($field_name, array $resource_types) {
    return array_reduce($resource_types, function ($carry, ResourceType $resource_type) use ($field_name) {
      if ($carry != $field_name) {
        // We already found the internal name.
        return $carry;
      }
      return $field_name === 'id' ? $this->getIdFieldName($resource_type) : $resource_type->getInternalName($field_name);
    }, $field_name);
  }

  /**
   * Determines if the given field or member name is filterable.
   *
   * @param string $external_name
   *   The external field or member name.
   * @param \Drupal\wotapi\ResourceType\ResourceType[] $resource_types
   *   The resource types to test.
   *
   * @return bool
   *   Whether the given field is present as a filterable member of the targeted
   *   resource objects.
   */
  protected function isMemberFilterable($external_name, array $resource_types) {
    return array_reduce($resource_types, function ($carry, ResourceType $resource_type) use ($external_name) {
      // @todo: remove the next line and uncomment the following one in https://www.drupal.org/project/wotapi/issues/3017047.
      return $carry ?: $external_name === 'id' || $resource_type->isFieldEnabled($resource_type->getInternalName($external_name));
      /*return $carry ?: in_array($external_name, ['id', 'type']) || $resource_type->isFieldEnabled($resource_type->getInternalName($external_name));*/
    }, FALSE);
  }

  /**
   * Get the referenceable ResourceTypes for a set of field definitions.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $definitions
   *   The resource types on which the reference field might exist.
   *
   * @return \Drupal\wotapi\ResourceType\ResourceType[]
   *   The referenceable target resource types.
   */
  protected function getReferenceableResourceTypes(array $definitions) {
    return array_reduce($definitions, function ($result, $definition) {
      $resource_types = array_filter(
        $this->collectResourceTypesForReference($definition)
      );
      $type_names = array_map(function ($resource_type) {
        /* @var \Drupal\wotapi\ResourceType\ResourceType $resource_type */
        return $resource_type->getTypeName();
      }, $resource_types);
      return array_merge($result, array_combine($type_names, $resource_types));
    }, []);
  }

  /**
   * Build a list of resource types depending on which bundles are referenced.
   *
   * @param \Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface $item_definition
   *   The reference definition.
   *
   * @return \Drupal\wotapi\ResourceType\ResourceType[]
   *   The list of resource types.
   */
  protected function collectResourceTypesForReference(FieldItemDataDefinitionInterface $item_definition) {
    $main_property_definition = $item_definition->getPropertyDefinition(
      $item_definition->getMainPropertyName()
    );

    // Check if the field is a flavor of an Entity Reference field.
    if (!$main_property_definition instanceof DataReferenceTargetDefinition) {
      return [];
    }
    $entity_type_id = $item_definition->getSetting('target_type');
    $handler_settings = $item_definition->getSetting('handler_settings');

    $has_target_bundles = isset($handler_settings['target_bundles']) && !empty($handler_settings['target_bundles']);
    $target_bundles = $has_target_bundles ?
      $handler_settings['target_bundles']
      : $this->getAllBundlesForEntityType($entity_type_id);

    return array_map(function ($bundle) use ($entity_type_id) {
      return $this->resourceTypeRepository->get($entity_type_id, $bundle);
    }, $target_bundles);
  }

  /**
   * Whether the given resources can be traversed to other resources.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType[] $resource_types
   *   The resources types to evaluate.
   *
   * @return bool
   *   TRUE if any one of the given resource types is traversable.
   *
   * @todo This class shouldn't be aware of entity types and their definitions.
   * Whether a resource can have properties to other resources is information
   * we ought to be able to discover on the ResourceType. However, we cannot
   * reliably determine this information with existing APIs. Entities may be
   * backed by various storages that are unable to perform queries across
   * references and certain storages may not be able to store references at all.
   */
  protected function resourceTypesAreTraversable(array $resource_types) {
    foreach ($resource_types as $resource_type) {
      $entity_type_definition = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId());
      if ($entity_type_definition->entityClassImplements(FieldableEntityInterface::class)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gets all bundle IDs for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type for which to get bundles.
   *
   * @return string[]
   *   The bundle IDs.
   */
  protected function getAllBundlesForEntityType($entity_type_id) {
    return array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type_id));
  }

  /**
   * Gets all unique reference property names from the given field definitions.
   *
   * @param \Drupal\Core\TypedData\ComplexDataDefinitionInterface[] $candidate_definitions
   *   A list of targeted field item definitions specified by the path.
   *
   * @return string[]
   *   The reference property names, if any.
   */
  protected static function getAllDataReferencePropertyNames(array $candidate_definitions) {
    $reference_property_names = array_reduce($candidate_definitions, function (array $reference_property_names, ComplexDataDefinitionInterface $definition) {
      $property_definitions = $definition->getPropertyDefinitions();
      foreach ($property_definitions as $property_name => $property_definition) {
        if ($property_definition instanceof DataReferenceDefinitionInterface) {
          $target_definition = $property_definition->getTargetDefinition();
          assert($target_definition instanceof EntityDataDefinitionInterface, 'Entity reference fields should only be able to reference entities.');
          $reference_property_names[] = $property_name . ':' . $target_definition->getEntityTypeId();
        }
      }
      return $reference_property_names;
    }, []);
    return array_unique($reference_property_names);
  }

  /**
   * Determines the reference property name for the remaining unresolved parts.
   *
   * @param \Drupal\Core\TypedData\ComplexDataDefinitionInterface[] $candidate_definitions
   *   A list of targeted field item definitions specified by the path.
   * @param string[] $remaining_parts
   *   The remaining path parts.
   * @param string[] $unresolved_path_parts
   *   The unresolved path parts.
   *
   * @return string
   *   The reference name.
   */
  protected static function getDataReferencePropertyName(array $candidate_definitions, array $remaining_parts, array $unresolved_path_parts) {
    $unique_reference_names = static::getAllDataReferencePropertyNames($candidate_definitions);
    if (count($unique_reference_names) > 1) {
      $choices = array_map(function ($reference_name) use ($unresolved_path_parts, $remaining_parts) {
        $prior_parts = array_slice($unresolved_path_parts, 0, count($unresolved_path_parts) - count($remaining_parts));
        return implode('.', array_merge($prior_parts, [$reference_name], $remaining_parts));
      }, $unique_reference_names);
      // @todo Add test coverage for this in https://www.drupal.org/project/wotapi/issues/2971281
      $message = sprintf('Ambiguous path. Try one of the following: %s, in place of the given path: %s', implode(', ', $choices), implode('.', $unresolved_path_parts));
      $cacheability = (new CacheableMetadata())->addCacheContexts(['url.query_args:filter', 'url.query_args:sort']);
      throw new CacheableBadRequestHttpException($cacheability, $message);
    }
    return $unique_reference_names[0];
  }

  /**
   * Determines if a path part targets a specific field delta.
   *
   * @param string $part
   *   The path part.
   *
   * @return bool
   *   TRUE if the part is an integer, FALSE otherwise.
   */
  protected static function isDelta($part) {
    return (bool) preg_match('/^[0-9]+$/', $part);
  }

  /**
   * Determines if a path part targets a field property, not a subsequent field.
   *
   * @param string $part
   *   The path part.
   * @param \Drupal\Core\TypedData\ComplexDataDefinitionInterface[] $candidate_definitions
   *   A list of targeted field item definitions which are specified by the
   *   path.
   *
   * @return bool
   *   TRUE if the part is a property of one of the candidate definitions, FALSE
   *   otherwise.
   */
  protected static function isCandidateDefinitionProperty($part, array $candidate_definitions) {
    $part = static::getPathPartPropertyName($part);
    foreach ($candidate_definitions as $definition) {
      if ($definition->getPropertyDefinition($part)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Determines if a path part targets a reference property.
   *
   * @param string $part
   *   The path part.
   * @param \Drupal\Core\TypedData\ComplexDataDefinitionInterface[] $candidate_definitions
   *   A list of targeted field item definitions which are specified by the
   *   path.
   *
   * @return bool
   *   TRUE if the part is a property of one of the candidate definitions, FALSE
   *   otherwise.
   */
  protected static function isCandidateDefinitionReferenceProperty($part, array $candidate_definitions) {
    $part = static::getPathPartPropertyName($part);
    foreach ($candidate_definitions as $definition) {
      $property = $definition->getPropertyDefinition($part);
      if ($property && $property instanceof DataReferenceDefinitionInterface) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gets the property name from an entity typed or untyped path part.
   *
   * A path part may contain an entity type specifier like `entity:node`. This
   * extracts the actual property name. If an entity type is not specified, then
   * the path part is simply returned. For example, both `foo` and `foo:bar`
   * will return `foo`.
   *
   * @param string $part
   *   A path part.
   *
   * @return string
   *   The property name from a path part.
   */
  protected static function getPathPartPropertyName($part) {
    return strpos($part, ':') !== FALSE ? explode(':', $part)[0] : $part;
  }

  /**
   * Gets the field access result for the 'view' operation.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The WOT:API resource type on which the field exists.
   * @param string $internal_field_name
   *   The field name for which access should be checked.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The 'view' access result.
   */
  protected function getFieldAccess(ResourceType $resource_type, $internal_field_name) {
    $definitions = $this->fieldManager->getFieldDefinitions($resource_type->getEntityTypeId(), $resource_type->getBundle());
    assert(isset($definitions[$internal_field_name]), 'The field name should have already been validated.');
    $field_definition = $definitions[$internal_field_name];
    $filter_access_results = $this->moduleHandler->invokeAll('wotapi_entity_field_filter_access', [$field_definition, \Drupal::currentUser()]);
    $filter_access_result = array_reduce($filter_access_results, function (AccessResultInterface $combined_result, AccessResultInterface $result) {
      return $combined_result->orIf($result);
    }, AccessResult::neutral());
    if (!$filter_access_result->isNeutral()) {
      return $filter_access_result;
    }
    $entity_access_control_handler = $this->entityTypeManager->getAccessControlHandler($resource_type->getEntityTypeId());
    $field_access = $entity_access_control_handler->fieldAccess('view', $field_definition, NULL, NULL, TRUE);
    return $filter_access_result->orIf($field_access);
  }

}
