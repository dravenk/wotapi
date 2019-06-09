<?php

namespace Drupal\wotapi\Controller;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\wotapi\Access\EntityAccessChecker;
use Drupal\wotapi\Context\FieldResolver;
use Drupal\wotapi\Entity\EntityValidationTrait;
use Drupal\wotapi\Access\TemporaryQueryGuard;
use Drupal\wotapi\Exception\EntityAccessDeniedHttpException;
use Drupal\wotapi\Exception\UnprocessableHttpEntityException;
use Drupal\wotapi\IncludeResolver;
use Drupal\wotapi\Normalizer\PropertiesFieldNormalizer;
use Drupal\wotapi\WotApiResource\IncludedData;
use Drupal\wotapi\WotApiResource\LinkCollection;
use Drupal\wotapi\WotApiResource\NullIncludedData;
use Drupal\wotapi\WotApiResource\ResourceIdentifier;
use Drupal\wotapi\WotApiResource\Link;
use Drupal\wotapi\WotApiResource\ResourceObject;
use Drupal\wotapi\WotApiResource\ResourceObjectData;
//use Drupal\wotapi\Normalizer\EntityReferenceFieldNormalizer;
use Drupal\wotapi\Query\Filter;
use Drupal\wotapi\Query\Sort;
use Drupal\wotapi\Query\OffsetPage;
use Drupal\wotapi\WotApiResource\Data;
use Drupal\wotapi\WotApiResource\WotApiDocumentTopLevel;
use Drupal\wotapi\ResourceResponse;
use Drupal\wotapi\ResourceType\ResourceType;
use Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\wotapi\Revisions\ResourceVersionRouteEnhancer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Process all entity requests.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 */
class EntityResource {

  use EntityValidationTrait;

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
   * The resource type repository.
   *
   * @var \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The include resolver.
   *
   * @var \Drupal\wotapi\IncludeResolver
   */
  protected $includeResolver;

  /**
   * The WOT:API entity access checker.
   *
   * @var \Drupal\wotapi\Access\EntityAccessChecker
   */
  protected $entityAccessChecker;

  /**
   * The WOT:API field resolver.
   *
   * @var \Drupal\wotapi\Context\FieldResolver
   */
  protected $fieldResolver;

  /**
   * The WOT:API serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $serializer;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * Instantiates a EntityResource object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity type field manager.
   * @param \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The WOT:API resource type repository.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\wotapi\IncludeResolver $include_resolver
   *   The include resolver.
   * @param \Drupal\wotapi\Access\EntityAccessChecker $entity_access_checker
   *   The WOT:API entity access checker.
   * @param \Drupal\wotapi\Context\FieldResolver $field_resolver
   *   The WOT:API field resolver.
   * @param \Symfony\Component\Serializer\SerializerInterface|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface $serializer
   *   The WOT:API serializer.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user account.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $field_manager, ResourceTypeRepositoryInterface $resource_type_repository, RendererInterface $renderer, EntityRepositoryInterface $entity_repository, IncludeResolver $include_resolver, EntityAccessChecker $entity_access_checker, FieldResolver $field_resolver, SerializerInterface $serializer, TimeInterface $time, AccountInterface $user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldManager = $field_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->renderer = $renderer;
    $this->entityRepository = $entity_repository;
    $this->includeResolver = $include_resolver;
    $this->entityAccessChecker = $entity_access_checker;
    $this->fieldResolver = $field_resolver;
    $this->serializer = $serializer;
    $this->time = $time;
    $this->user = $user;
  }

  /**
   * Gets the individual entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The loaded entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\wotapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\wotapi\Exception\EntityAccessDeniedHttpException
   *   Thrown when access to the entity is not allowed.
   */
  public function getIndividual(EntityInterface $entity, Request $request) {
    $resource_object = $this->entityAccessChecker->getAccessCheckedResourceObject($entity);
    if ($resource_object instanceof EntityAccessDeniedHttpException) {
      throw $resource_object;
    }
    $primary_data = new ResourceObjectData([$resource_object], 1);
    $response = $this->buildWrappedResponse($primary_data, $request, new NullIncludedData());
    return $response;
  }

  /**
   * Gets the collection of entities.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The WOT:API resource type for the request to be served.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\wotapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Http\Exception\CacheableBadRequestHttpException
   *   Thrown when filtering on a config entity which does not support it.
   */
  public function getCollection(ResourceType $resource_type, Request $request) {
    // Instantiate the query for the filtering.
    $entity_type_id = $resource_type->getEntityTypeId();

    $params = $this->getWotApiParams($request, $resource_type);
    $query_cacheability = new CacheableMetadata();
    $query = $this->getCollectionQuery($resource_type, $params, $query_cacheability);

    // If the request is for the latest revision, toggle it on entity query.
    if ($request->get(ResourceVersionRouteEnhancer::WORKING_COPIES_REQUESTED, FALSE)) {
      $query->latestRevision();
    }

    try {
      $results = $this->executeQueryInRenderContext(
        $query,
        $query_cacheability
      );
    }
    catch (\LogicException $e) {
      // Ensure good DX when an entity query involves a config entity type.
      // For example: getting users with a particular role, which is a config
      // entity type: https://www.drupal.org/project/wotapi/issues/2959445.
      // @todo Remove the message parsing in https://www.drupal.org/project/drupal/issues/3028967.
      if (strpos($e->getMessage(), 'Getting the base fields is not supported for entity type') === 0) {
        preg_match('/entity type (.*)\./', $e->getMessage(), $matches);
        $config_entity_type_id = $matches[1];
        $cacheability = (new CacheableMetadata())->addCacheContexts(['url.path', 'url.query_args:filter']);
        throw new CacheableBadRequestHttpException($cacheability, sprintf("Filtering on config entities is not supported by Drupal's entity API. You tried to filter on a %s config entity.", $config_entity_type_id));
      }
      else {
        throw $e;
      }
    }

    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    // We request N+1 items to find out if there is a next page for the pager.
    // We may need to remove that extra item before loading the entities.
    $pager_size = $query->getMetaData('pager_size');
    if ($has_next_page = $pager_size < count($results)) {
      // Drop the last result.
      array_pop($results);
    }
    // Each item of the collection data contains an array with 'entity' and
    // 'access' elements.
    $collection_data = $this->loadEntitiesWithAccess($storage, $results, $request->get(ResourceVersionRouteEnhancer::WORKING_COPIES_REQUESTED, FALSE));
    $primary_data = new ResourceObjectData($collection_data);
    $primary_data->setHasNextPage($has_next_page);

    // Calculate all the results and pass into a WOT:API Data object.
    $count_query_cacheability = new CacheableMetadata();
    if ($resource_type->includeCount()) {
      $count_query = $this->getCollectionCountQuery($resource_type, $params, $count_query_cacheability);
      $total_results = $this->executeQueryInRenderContext(
        $count_query,
        $count_query_cacheability
      );

      $primary_data->setTotalCount($total_results);
    }

    $response = $this->respondWithCollection($primary_data, new NullIncludedData(), $request, $resource_type, $params[OffsetPage::KEY_NAME]);

    $response->addCacheableDependency($query_cacheability);
    $response->addCacheableDependency($count_query_cacheability);
    $response->addCacheableDependency((new CacheableMetadata())
      ->addCacheContexts([
        'url.query_args:filter',
        'url.query_args:sort',
        'url.query_args:page',
      ]));

    if ($resource_type->isVersionable()) {
      $response->addCacheableDependency((new CacheableMetadata())->addCacheContexts([ResourceVersionRouteEnhancer::CACHE_CONTEXT]));
    }

    return $response;
  }

  /**
   * Executes the query in a render context, to catch bubbled cacheability.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to execute to get the return results.
   * @param \Drupal\Core\Cache\CacheableMetadata $query_cacheability
   *   The value object to carry the query cacheability.
   *
   * @return int|array
   *   Returns an integer for count queries or an array of IDs. The values of
   *   the array are always entity IDs. The keys will be revision IDs if the
   *   entity supports revision and entity IDs if not.
   *
   * @see node_query_node_access_alter()
   * @see https://www.drupal.org/project/drupal/issues/2557815
   * @see https://www.drupal.org/project/drupal/issues/2794385
   * @todo Remove this after https://www.drupal.org/project/drupal/issues/3028976 is fixed.
   */
  protected function executeQueryInRenderContext(QueryInterface $query, CacheableMetadata $query_cacheability) {
    $context = new RenderContext();
    $results = $this->renderer->executeInRenderContext($context, function () use ($query) {
      return $query->execute();
    });
    if (!$context->isEmpty()) {
      $query_cacheability->addCacheableDependency($context->pop());
    }
    return $results;
  }

  /**
   * Gets the related resource.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The WOT:API resource type for the request to be served.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The requested entity.
   * @param string $related
   *   The related field name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\wotapi\ResourceResponse
   *   The response.
   */
  public function getRelated(ResourceType $resource_type, FieldableEntityInterface $entity, $related, Request $request) {
    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
    $field_list = $entity->get($resource_type->getInternalName($related));

    // Remove the entities pointing to a resource that may be disabled. Even
    // though the normalizer skips disabled references, we can avoid unnecessary
    // work by checking here too.
    /* @var \Drupal\Core\Entity\EntityInterface[] $referenced_entities */
    $referenced_entities = array_filter(
      $field_list->referencedEntities(),
      function (EntityInterface $entity) {
        return (bool) $this->resourceTypeRepository->get(
          $entity->getEntityTypeId(),
          $entity->bundle()
        );
      }
    );
    $collection_data = [];
    foreach ($referenced_entities as $referenced_entity) {
      $collection_data[] = $this->entityAccessChecker->getAccessCheckedResourceObject($referenced_entity);
    }
    $primary_data = new ResourceObjectData($collection_data, $field_list->getFieldDefinition()->getFieldStorageDefinition()->getCardinality());
    $response = $this->buildWrappedResponse($primary_data, $request, new NullIncludedData());

    // $response does not contain the entity list cache tag. We add the
    // cacheable metadata for the finite list of entities in the relationship.
    $response->addCacheableDependency($entity);

    return $response;
  }

  /**
   * Gets the relationship of an entity.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The base WOT:API resource type for the request to be served.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The requested entity.
   * @param string $related
   *   The related field name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param int $response_code
   *   The response code. Defaults to 200.
   *
   * @return \Drupal\wotapi\ResourceResponse
   *   The response.
   */
  public function getProperties(ResourceType $resource_type, FieldableEntityInterface $entity, $related, Request $request, $response_code = 200) {
    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
    $field_list = $entity->get($resource_type->getInternalName($related));
    // Access will have already been checked by the RelationshipFieldAccess
    // service, so we don't need to call ::getAccessCheckedResourceObject().
    $resource_object = ResourceObject::createFromEntity($resource_type, $entity);
//    $relationship_object_urls = EntityReferenceFieldNormalizer::getRelationshipLinks($resource_object, $related);
    $relationship_object_urls = PropertiesFieldNormalizer::getPropertiesLinks($resource_object, $related);

    $response = $this->buildWrappedResponse($field_list, $request, new NullIncludedData(), $response_code, [], array_reduce(array_keys($relationship_object_urls), function (LinkCollection $links, $key) use ($relationship_object_urls) {
      return $links->withLink($key, new Link(new CacheableMetadata(), $relationship_object_urls[$key], [$key]));
    }, new LinkCollection([])));
    // Add the host entity as a cacheable dependency.
    $response->addCacheableDependency($entity);
    return $response;
  }
//
//  /**
//   * Adds a relationship to a to-many relationship.
//   *
//   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
//   *   The base WOT:API resource type for the request to be served.
//   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
//   *   The requested entity.
//   * @param string $related
//   *   The related field name.
//   * @param \Symfony\Component\HttpFoundation\Request $request
//   *   The request object.
//   *
//   * @return \Drupal\wotapi\ResourceResponse
//   *   The response.
//   *
//   * @throws \Drupal\wotapi\Exception\EntityAccessDeniedHttpException
//   *   Thrown when the current user is not allowed to PATCH the selected
//   *   field(s).
//   * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
//   *   Thrown when POSTing to a "to-one" relationship.
//   * @throws \Drupal\Core\Entity\EntityStorageException
//   *   Thrown when the underlying entity cannot be saved.
//   * @throws \Drupal\wotapi\Exception\UnprocessableHttpEntityException
//   *   Thrown when the updated entity does not pass validation.
//   */
//  public function addToRelationshipData(ResourceType $resource_type, FieldableEntityInterface $entity, $related, Request $request) {
//    $resource_identifiers = $this->deserialize($resource_type, $request, ResourceIdentifier::class, $related);
//    $related = $resource_type->getInternalName($related);
//    // According to the specification, you are only allowed to POST to a
//    // relationship if it is a to-many relationship.
//    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
//    $field_list = $entity->{$related};
//    /* @var \Drupal\field\Entity\FieldConfig $field_definition */
//    $field_definition = $field_list->getFieldDefinition();
//    $is_multiple = $field_definition->getFieldStorageDefinition()->isMultiple();
//    if (!$is_multiple) {
//      throw new ConflictHttpException(sprintf('You can only POST to to-many properties. %s is a to-one relationship.', $related));
//    }
//
//    $original_resource_identifiers = ResourceIdentifier::toResourceIdentifiersWithArityRequired($field_list);
//    $new_resource_identifiers = array_udiff(
//      ResourceIdentifier::deduplicate(array_merge($original_resource_identifiers, $resource_identifiers)),
//      $original_resource_identifiers,
//      [ResourceIdentifier::class, 'compare']
//    );
//
//    // There are no properties that need to be added so we can exit early.
//    if (empty($new_resource_identifiers)) {
//      $status = static::relationshipResponseRequiresBody($resource_identifiers, $original_resource_identifiers) ? 200 : 204;
//      return $this->getRelationship($resource_type, $entity, $related, $request, $status);
//    }
//
//    $main_property_name = $field_definition->getItemDefinition()->getMainPropertyName();
//    foreach ($new_resource_identifiers as $new_resource_identifier) {
//      $new_field_value = [$main_property_name => $this->getEntityFromResourceIdentifier($new_resource_identifier)->id()];
//      // Remove `arity` from the received extra properties, otherwise this
//      // will fail field validation.
//      $new_field_value += array_diff_key($new_resource_identifier->getMeta(), array_flip([ResourceIdentifier::ARITY_KEY]));
//      $field_list->appendItem($new_field_value);
//    }
//
//    $this->validate($entity);
//    $entity->save();
//
//    $final_resource_identifiers = ResourceIdentifier::toResourceIdentifiersWithArityRequired($field_list);
//    $status = static::relationshipResponseRequiresBody($resource_identifiers, $final_resource_identifiers) ? 200 : 204;
//    return $this->getRelationship($resource_type, $entity, $related, $request, $status);
//  }

//  /**
//   * Updates the relationship of an entity.
//   *
//   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
//   *   The base WOT:API resource type for the request to be served.
//   * @param \Drupal\Core\Entity\EntityInterface $entity
//   *   The requested entity.
//   * @param string $related
//   *   The related field name.
//   * @param \Symfony\Component\HttpFoundation\Request $request
//   *   The request object.
//   *
//   * @return \Drupal\wotapi\ResourceResponse
//   *   The response.
//   *
//   * @throws \Drupal\Core\Entity\EntityStorageException
//   *   Thrown when the underlying entity cannot be saved.
//   * @throws \Drupal\wotapi\Exception\UnprocessableHttpEntityException
//   *   Thrown when the updated entity does not pass validation.
//   */
//  public function replaceRelationshipData(ResourceType $resource_type, EntityInterface $entity, $related, Request $request) {
//    $resource_identifiers = $this->deserialize($resource_type, $request, ResourceIdentifier::class, $related);
//    $related = $resource_type->getInternalName($related);
//    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $resource_identifiers */
//    // According to the specification, PATCH works a little bit different if the
//    // relationship is to-one or to-many.
//    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
//    $field_list = $entity->{$related};
//    $field_definition = $field_list->getFieldDefinition();
//    $is_multiple = $field_definition->getFieldStorageDefinition()->isMultiple();
//    $method = $is_multiple ? 'doPatchMultipleRelationship' : 'doPatchIndividualRelationship';
//    $this->{$method}($entity, $resource_identifiers, $field_definition);
//    $this->validate($entity);
//    $entity->save();
//    $requires_response = static::relationshipResponseRequiresBody($resource_identifiers, ResourceIdentifier::toResourceIdentifiersWithArityRequired($field_list));
//    return $this->getRelationship($resource_type, $entity, $related, $request, $requires_response ? 200 : 204);
//  }
//
//  /**
//   * Update a to-one relationship.
//   *
//   * @param \Drupal\Core\Entity\EntityInterface $entity
//   *   The requested entity.
//   * @param \Drupal\wotapi\WotApiResource\ResourceIdentifier[] $resource_identifiers
//   *   The client-sent resource identifiers which should be set on the given
//   *   entity. Should be an empty array or an array with a single value.
//   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
//   *   The field definition of the entity field to be updated.
//   *
//   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
//   *   Thrown when a "to-one" relationship is not provided.
//   */
//  protected function doPatchIndividualRelationship(EntityInterface $entity, array $resource_identifiers, FieldDefinitionInterface $field_definition) {
//    if (count($resource_identifiers) > 1) {
//      throw new BadRequestHttpException(sprintf('Provide a single relationship so to-one relationship fields (%s).', $field_definition->getName()));
//    }
//    $this->doPatchMultipleRelationship($entity, $resource_identifiers, $field_definition);
//  }
//
//  /**
//   * Update a to-many relationship.
//   *
//   * @param \Drupal\Core\Entity\EntityInterface $entity
//   *   The requested entity.
//   * @param \Drupal\wotapi\WotApiResource\ResourceIdentifier[] $resource_identifiers
//   *   The client-sent resource identifiers which should be set on the given
//   *   entity.
//   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
//   *   The field definition of the entity field to be updated.
//   */
//  protected function doPatchMultipleRelationship(EntityInterface $entity, array $resource_identifiers, FieldDefinitionInterface $field_definition) {
//    $main_property_name = $field_definition->getItemDefinition()->getMainPropertyName();
//    $entity->{$field_definition->getName()} = array_map(function (ResourceIdentifier $resource_identifier) use ($main_property_name) {
//      $field_properties = [$main_property_name => $this->getEntityFromResourceIdentifier($resource_identifier)->id()];
//      // Remove `arity` from the received extra properties, otherwise this
//      // will fail field validation.
//      $field_properties += array_diff_key($resource_identifier->getMeta(), array_flip([ResourceIdentifier::ARITY_KEY]));
//      return $field_properties;
//    }, $resource_identifiers);
//  }

//  /**
//   * Deletes the relationship of an entity.
//   *
//   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
//   *   The base WOT:API resource type for the request to be served.
//   * @param \Drupal\Core\Entity\EntityInterface $entity
//   *   The requested entity.
//   * @param string $related
//   *   The related field name.
//   * @param \Symfony\Component\HttpFoundation\Request $request
//   *   The request object.
//   *
//   * @return \Drupal\wotapi\ResourceResponse
//   *   The response.
//   *
//   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
//   *   Thrown when not body was provided for the DELETE operation.
//   * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
//   *   Thrown when deleting a "to-one" relationship.
//   * @throws \Drupal\Core\Entity\EntityStorageException
//   *   Thrown when the underlying entity cannot be saved.
//   */
//  public function removeFromRelationshipData(ResourceType $resource_type, EntityInterface $entity, $related, Request $request) {
//    $resource_identifiers = $this->deserialize($resource_type, $request, ResourceIdentifier::class, $related);
//    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
//    $field_list = $entity->{$related};
//    $is_multiple = $field_list->getFieldDefinition()
//      ->getFieldStorageDefinition()
//      ->isMultiple();
//    if (!$is_multiple) {
//      throw new ConflictHttpException(sprintf('You can only DELETE from to-many properties. %s is a to-one relationship.', $related));
//    }
//
//    // Compute the list of current values and remove the ones in the payload.
//    $original_resource_identifiers = ResourceIdentifier::toResourceIdentifiersWithArityRequired($field_list);
//    $removed_resource_identifiers = array_uintersect($resource_identifiers, $original_resource_identifiers, [ResourceIdentifier::class, 'compare']);
//    $deltas_to_be_removed = [];
//    foreach ($removed_resource_identifiers as $removed_resource_identifier) {
//      foreach ($original_resource_identifiers as $delta => $existing_resource_identifier) {
//        // Identify the field item deltas which should be removed.
//        if (ResourceIdentifier::isDuplicate($removed_resource_identifier, $existing_resource_identifier)) {
//          $deltas_to_be_removed[] = $delta;
//        }
//      }
//    }
//    // Field item deltas are reset when an item is removed. This removes
//    // items in descending order so that the deltas yet to be removed will
//    // continue to exist.
//    rsort($deltas_to_be_removed);
//    foreach ($deltas_to_be_removed as $delta) {
//      $field_list->removeItem($delta);
//    }
//
//    // Save the entity and return the response object.
//    static::validate($entity);
//    $entity->save();
//    return $this->getRelationship($resource_type, $entity, $related, $request, 204);
//  }

//  /**
//   * Deserializes a request body, if any.
//   *
//   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
//   *   The WOT:API resource type for the current request.
//   * @param \Symfony\Component\HttpFoundation\Request $request
//   *   The request object.
//   * @param string $class
//   *   The class into which the request data needs to be deserialized.
//   * @param string $relationship_field_name
//   *   The public relationship field name of the data to be deserialized if the
//   *   incoming request is for a relationship update. Not required for non-
//   *   relationship requests.
//   *
//   * @return array
//   *   An object normalization.
//   *
//   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
//   *   Thrown if the request body cannot be decoded, or when no request body was
//   *   provided with a POST or PATCH request.
//   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
//   *   Thrown if the request body cannot be denormalized.
//   */
//  protected function deserialize(ResourceType $resource_type, Request $request, $class, $relationship_field_name = NULL) {
//    assert($class === WotApiDocumentTopLevel::class || $class === ResourceIdentifier::class && !empty($relationship_field_name) && is_string($relationship_field_name));
//    $received = (string) $request->getContent();
//    if (!$received) {
//      assert($request->isMethod('POST') || $request->isMethod('PATCH') || $request->isMethod('DELETE'));
//      if ($request->isMethod('DELETE') && $relationship_field_name) {
//        throw new BadRequestHttpException(sprintf('You need to provide a body for DELETE operations on a relationship (%s).', $relationship_field_name));
//      }
//      else {
//        throw new BadRequestHttpException('Empty request body.');
//      }
//    }
//    // First decode the request data. We can then determine if the serialized
//    // data was malformed.
//    try {
//      $decoded = $this->serializer->decode($received, 'api_json');
//    }
//    catch (UnexpectedValueException $e) {
//      // If an exception was thrown at this stage, there was a problem decoding
//      // the data. Throw a 400 HTTP exception.
//      throw new BadRequestHttpException($e->getMessage());
//    }
//
//    try {
//      $context = ['resource_type' => $resource_type];
//      if ($relationship_field_name) {
//        $context['related'] = $resource_type->getInternalName($relationship_field_name);
//      }
//      return $this->serializer->denormalize($decoded, $class, 'api_json', $context);
//    }
//    // These two serialization exception types mean there was a problem with
//    // the structure of the decoded data and it's not valid.
//    catch (UnexpectedValueException $e) {
//      throw new UnprocessableHttpEntityException($e->getMessage());
//    }
//    catch (InvalidArgumentException $e) {
//      throw new UnprocessableHttpEntityException($e->getMessage());
//    }
//  }

  /**
   * Gets a basic query for a collection.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The base WOT:API resource type for the query.
   * @param array $params
   *   The parameters for the query.
   * @param \Drupal\Core\Cache\CacheableMetadata $query_cacheability
   *   Collects cacheability for the query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   A new query.
   */
  protected function getCollectionQuery(ResourceType $resource_type, array $params, CacheableMetadata $query_cacheability) {
    $entity_type = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId());
    $entity_storage = $this->entityTypeManager->getStorage($resource_type->getEntityTypeId());

    $query = $entity_storage->getQuery();

    // Ensure that access checking is performed on the query.
    $query->accessCheck(TRUE);

//    // Compute and apply an entity query condition from the filter parameter.
//    if (isset($params[Filter::KEY_NAME]) && $filter = $params[Filter::KEY_NAME]) {
//      $query->condition($filter->queryCondition($query));
//      TemporaryQueryGuard::setFieldManager($this->fieldManager);
//      TemporaryQueryGuard::setModuleHandler(\Drupal::moduleHandler());
//      TemporaryQueryGuard::applyAccessControls($filter, $query, $query_cacheability);
//    }

    // Apply any sorts to the entity query.
    if (isset($params[Sort::KEY_NAME]) && $sort = $params[Sort::KEY_NAME]) {
      foreach ($sort->fields() as $field) {
        $path = $this->fieldResolver->resolveInternalEntityQueryPath($resource_type->getEntityTypeId(), $resource_type->getBundle(), $field[Sort::PATH_KEY]);
        $direction = isset($field[Sort::DIRECTION_KEY]) ? $field[Sort::DIRECTION_KEY] : 'ASC';
        $langcode = isset($field[Sort::LANGUAGE_KEY]) ? $field[Sort::LANGUAGE_KEY] : NULL;
        $query->sort($path, $direction, $langcode);
      }
    }

    // Apply any pagination options to the query.
    if (isset($params[OffsetPage::KEY_NAME])) {
      $pagination = $params[OffsetPage::KEY_NAME];
    }
    else {
      $pagination = new OffsetPage(OffsetPage::DEFAULT_OFFSET, OffsetPage::SIZE_MAX);
    }
    // Add one extra element to the page to see if there are more pages needed.
    $query->range($pagination->getOffset(), $pagination->getSize() + 1);
    $query->addMetaData('pager_size', (int) $pagination->getSize());

    // Limit this query to the bundle type for this resource.
    $bundle = $resource_type->getBundle();
    if ($bundle && ($bundle_key = $entity_type->getKey('bundle'))) {
      $query->condition(
        $bundle_key, $bundle
      );
    }

    return $query;
  }

  /**
   * Gets a basic query for a collection count.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The base WOT:API resource type for the query.
   * @param array $params
   *   The parameters for the query.
   * @param \Drupal\Core\Cache\CacheableMetadata $query_cacheability
   *   Collects cacheability for the query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   A new query.
   */
  protected function getCollectionCountQuery(ResourceType $resource_type, array $params, CacheableMetadata $query_cacheability) {
    // Reset the range to get all the available results.
    return $this->getCollectionQuery($resource_type, $params, $query_cacheability)->range()->count();
  }

  /**
   * Loads the entity targeted by a resource identifier.
   *
   * @param \Drupal\wotapi\WotApiResource\ResourceIdentifier $resource_identifier
   *   A resource identifier.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity targeted by a resource identifier.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown if the given resource identifier targets a resource type or
   *   resource which does not exist.
   */
  protected function getEntityFromResourceIdentifier(ResourceIdentifier $resource_identifier) {
    $resource_type_name = $resource_identifier->getTypeName();
    if (!($target_resource_type = $this->resourceTypeRepository->getByTypeName($resource_type_name))) {
      throw new BadRequestHttpException("The resource type `{$resource_type_name}` does not exist.");
    }
    $id = $resource_identifier->getId();
    if (!($targeted_resource = $this->entityRepository->loadEntityByUuid($target_resource_type->getEntityTypeId(), $id))) {
      throw new BadRequestHttpException("The targeted `{$resource_type_name}` resource with ID `{$id}` does not exist.");
    }
    return $targeted_resource;
  }

//  /**
//   * Determines if the client needs to be updated with new relationship data.
//   *
//   * @param array $received_resource_identifiers
//   *   The array of resource identifiers given by the client.
//   * @param array $final_resource_identifiers
//   *   The final array of resource identifiers after applying the requested
//   *   changes.
//   *
//   * @return bool
//   *   Whether the final array of resource identifiers is different than the
//   *   client-sent data.
//   */
//  protected static function relationshipResponseRequiresBody(array $received_resource_identifiers, array $final_resource_identifiers) {
//    return !empty(array_udiff($final_resource_identifiers, $received_resource_identifiers, [ResourceIdentifier::class, 'compare']));
//  }

  /**
   * Builds a response with the appropriate wrapped document.
   *
   * @param mixed $data
   *   The data to wrap.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\wotapi\WotApiResource\IncludedData $includes
   *   The resources to be included in the document. Use NullData if
   *   there should be no included resources in the document.
   * @param int $response_code
   *   The response code.
   * @param array $headers
   *   An array of response headers.
   * @param \Drupal\wotapi\WotApiResource\LinkCollection $links
   *   The URLs to which to link. A 'self' link is added automatically.
   * @param array $meta
   *   (optional) The top-level metadata.
   *
   * @return \Drupal\wotapi\ResourceResponse
   *   The response.
   */
  protected function buildWrappedResponse($data, Request $request, IncludedData $includes, $response_code = 200, array $headers = [], LinkCollection $links = NULL, array $meta = []) {
    assert($data instanceof Data || $data instanceof FieldItemListInterface);
    $links = ($links ?: new LinkCollection([]));
//    if (!$links->hasLinkWithKey('self')) {
//      $self_link = new Link(new CacheableMetadata(), self::getRequestLink($request), ['self']);
//      $links = $links->withLink('self', $self_link);
//    }
    $response = new ResourceResponse(new WotApiDocumentTopLevel($data, $includes, $links, $meta), $response_code, $headers);
    $cacheability = (new CacheableMetadata())->addCacheContexts([
      // Make sure that different sparse fieldsets are cached differently.
      'url.query_args:fields',
      // Make sure that different sets of includes are cached differently.
      'url.query_args:include',
    ]);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  /**
   * Respond with an entity collection.
   *
   * @param \Drupal\wotapi\WotApiResource\ResourceObjectData $primary_data
   *   The collection of entities.
   * @param \Drupal\wotapi\WotApiResource\IncludedData|\Drupal\wotapi\WotApiResource\NullIncludedData $includes
   *   The resources to be included in the document.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The base WOT:API resource type for the request to be served.
   * @param \Drupal\wotapi\Query\OffsetPage $page_param
   *   The pagination parameter for the requested collection.
   *
   * @return \Drupal\wotapi\ResourceResponse
   *   The response.
   */
  protected function respondWithCollection(ResourceObjectData $primary_data, Data $includes, Request $request, ResourceType $resource_type, OffsetPage $page_param) {
    assert(Inspector::assertAllObjects([$includes], IncludedData::class, NullIncludedData::class));
    $link_context = [
      'has_next_page' => $primary_data->hasNextPage(),
    ];
    $meta = [];
    if ($resource_type->includeCount()) {
      $link_context['total_count'] = $meta['count'] = $primary_data->getTotalCount();
    }
    $collection_links = self::getPagerLinks($request, $page_param, $link_context);
    $response = $this->buildWrappedResponse($primary_data, $request, $includes, 200, [], $collection_links, $meta);

    // When a new change to any entity in the resource happens, we cannot ensure
    // the validity of this cached list. Add the list tag to deal with that.
    $list_tag = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId())
      ->getListCacheTags();
    $response->getCacheableMetadata()->addCacheTags($list_tag);
    foreach ($primary_data as $entity) {
      $response->addCacheableDependency($entity);
    }
    return $response;
  }
//
//  /**
//   * Gets includes for the given response data.
//   *
//   * @param \Symfony\Component\HttpFoundation\Request $request
//   *   The request object.
//   * @param \Drupal\wotapi\WotApiResource\ResourceObject|\Drupal\wotapi\WotApiResource\ResourceObjectData $data
//   *   The response data from which to resolve includes.
//   *
//   * @return \Drupal\wotapi\WotApiResource\Data
//   *   A Data object to be included or a NullData object if the request does not
//   *   specify any include paths.
//   *
//   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
//   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
//   */
//  public function getIncludes(Request $request, $data) {
//    assert($data instanceof ResourceObject || $data instanceof ResourceObjectData);
//    return $request->query->has('include') && ($include_parameter = $request->query->get('include')) && !empty($include_parameter)
//      ? $this->includeResolver->resolve($data, $include_parameter)
//      : new NullIncludedData();
//  }

  /**
   * Build a collection of the entities to respond with and access objects.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage to load the entities from.
   * @param int[] $ids
   *   An array of entity IDs, keyed by revision ID if the entity type is
   *   revisionable.
   * @param bool $load_latest_revisions
   *   Whether to load the latest revisions instead of the defaults.
   *
   * @return array
   *   An array of loaded entities and/or an access exceptions.
   */
  protected function loadEntitiesWithAccess(EntityStorageInterface $storage, array $ids, $load_latest_revisions) {
    $output = [];
    if ($load_latest_revisions) {
      assert($storage instanceof RevisionableStorageInterface);
      $entities = $storage->loadMultipleRevisions(array_keys($ids));
    }
    else {
      $entities = $storage->loadMultiple($ids);
    }
    foreach ($entities as $entity) {
      $output[$entity->id()] = $this->entityAccessChecker->getAccessCheckedResourceObject($entity);
    }
    return array_values($output);
  }

  /**
   * Checks if the given entity exists.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to test existence.
   *
   * @return bool
   *   Whether the entity already has been created.
   */
  protected function entityExists(EntityInterface $entity) {
    $entity_storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    return !empty($entity_storage->loadByProperties([
      'uuid' => $entity->uuid(),
    ]));
  }

  /**
   * Extracts WOT:API query parameters from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The WOT:API resource type.
   *
   * @return array
   *   An array of WOT:API parameters like `sort` and `filter`.
   */
  protected function getWotApiParams(Request $request, ResourceType $resource_type) {
    if ($request->query->has('filter')) {
      $params[Filter::KEY_NAME] = Filter::createFromQueryParameter($request->query->get('filter'), $resource_type, $this->fieldResolver);
    }
    if ($request->query->has('sort')) {
      $params[Sort::KEY_NAME] = Sort::createFromQueryParameter($request->query->get('sort'));
    }
    if ($request->query->has('page')) {
      $params[OffsetPage::KEY_NAME] = OffsetPage::createFromQueryParameter($request->query->get('page'));
    }
    else {
      $params[OffsetPage::KEY_NAME] = OffsetPage::createFromQueryParameter(['page' => ['offset' => OffsetPage::DEFAULT_OFFSET, 'limit' => OffsetPage::SIZE_MAX]]);
    }
    return $params;
  }

  /**
   * Get the full URL for a given request object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array|null $query
   *   The query parameters to use. Leave it empty to get the query from the
   *   request object.
   *
   * @return \Drupal\Core\Url
   *   The full URL.
   */
  protected static function getRequestLink(Request $request, $query = NULL) {
    if ($query === NULL) {
      return Url::fromUri($request->getUri());
    }

    $uri_without_query_string = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo();
    return Url::fromUri($uri_without_query_string)->setOption('query', $query);
  }

  /**
   * Get the pager links for a given request object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\wotapi\Query\OffsetPage $page_param
   *   The current pagination parameter for the requested collection.
   * @param array $link_context
   *   An associative array with extra data to build the links.
   *
   * @return \Drupal\wotapi\WotApiResource\LinkCollection
   *   An LinkCollection, with:
   *   - a 'next' key if it is not the last page;
   *   - 'prev' and 'first' keys if it's not the first page.
   */
  protected static function getPagerLinks(Request $request, OffsetPage $page_param, array $link_context = []) {
    $pager_links = new LinkCollection([]);
    if (!empty($link_context['total_count']) && !$total = (int) $link_context['total_count']) {
      return $pager_links;
    }
    /* @var \Drupal\wotapi\Query\OffsetPage $page_param */
    $offset = $page_param->getOffset();
    $size = $page_param->getSize();
    if ($size <= 0) {
      $cacheability = (new CacheableMetadata())->addCacheContexts(['url.query_args:page']);
      throw new CacheableBadRequestHttpException($cacheability, sprintf('The page size needs to be a positive integer.'));
    }
    $query = (array) $request->query->getIterator();
    // Check if this is not the last page.
    if ($link_context['has_next_page']) {
      $next_url = static::getRequestLink($request, static::getPagerQueries('next', $offset, $size, $query));
      $pager_links = $pager_links->withLink('next', new Link(new CacheableMetadata(), $next_url, ['next']));

      if (!empty($total)) {
        $last_url = static::getRequestLink($request, static::getPagerQueries('last', $offset, $size, $query, $total));
        $pager_links = $pager_links->withLink('last', new Link(new CacheableMetadata(), $last_url, ['last']));
      }
    }

    // Check if this is not the first page.
    if ($offset > 0) {
      $first_url = static::getRequestLink($request, static::getPagerQueries('first', $offset, $size, $query));
      $pager_links = $pager_links->withLink('first', new Link(new CacheableMetadata(), $first_url, ['first']));
      $prev_url = static::getRequestLink($request, static::getPagerQueries('prev', $offset, $size, $query));
      $pager_links = $pager_links->withLink('prev', new Link(new CacheableMetadata(), $prev_url, ['prev']));
    }

    return $pager_links;
  }

  /**
   * Get the query param array.
   *
   * @param string $link_id
   *   The name of the pagination link requested.
   * @param int $offset
   *   The starting index.
   * @param int $size
   *   The pagination page size.
   * @param array $query
   *   The query parameters.
   * @param int $total
   *   The total size of the collection.
   *
   * @return array
   *   The pagination query param array.
   */
  protected static function getPagerQueries($link_id, $offset, $size, array $query = [], $total = 0) {
    $extra_query = [];
    switch ($link_id) {
      case 'next':
        $extra_query = [
          'page' => [
            'offset' => $offset + $size,
            'limit' => $size,
          ],
        ];
        break;

      case 'first':
        $extra_query = [
          'page' => [
            'offset' => 0,
            'limit' => $size,
          ],
        ];
        break;

      case 'last':
        if ($total) {
          $extra_query = [
            'page' => [
              'offset' => (ceil($total / $size) - 1) * $size,
              'limit' => $size,
            ],
          ];
        }
        break;

      case 'prev':
        $extra_query = [
          'page' => [
            'offset' => max($offset - $size, 0),
            'limit' => $size,
          ],
        ];
        break;
    }
    return array_merge($query, $extra_query);
  }

}
