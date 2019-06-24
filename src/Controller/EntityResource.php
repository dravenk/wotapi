<?php

namespace Drupal\wotapi\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\wotapi\Access\EntityAccessChecker;
use Drupal\wotapi\Context\FieldResolver;
use Drupal\wotapi\Entity\EntityValidationTrait;
use Drupal\wotapi\Exception\EntityAccessDeniedHttpException;
use Drupal\wotapi\Normalizer\PropertiesFieldNormalizer;
use Drupal\wotapi\WotApiResource\LinkCollection;
use Drupal\wotapi\WotApiResource\ResourceIdentifier;
use Drupal\wotapi\WotApiResource\Link;
use Drupal\wotapi\WotApiResource\ResourceObject;
use Drupal\wotapi\WotApiResource\ResourceObjectData;
use Drupal\wotapi\WotApiResource\Data;
use Drupal\wotapi\WotApiResource\WotApiDocumentTopLevel;
use Drupal\wotapi\ResourceResponse;
use Drupal\wotapi\ResourceType\ResourceType;
use Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Process all entity requests.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
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

//  /**
//   * The include resolver.
//   *
//   * @var \Drupal\wotapi\IncludeResolver
//   */
//  protected $includeResolver;

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
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $field_manager, ResourceTypeRepositoryInterface $resource_type_repository, RendererInterface $renderer, EntityRepositoryInterface $entity_repository, EntityAccessChecker $entity_access_checker, FieldResolver $field_resolver, SerializerInterface $serializer, TimeInterface $time, AccountInterface $user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldManager = $field_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->renderer = $renderer;
    $this->entityRepository = $entity_repository;
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
    $response = $this->buildWrappedResponse($primary_data, $request);
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

//    $params = $this->getWotApiParams($request, $resource_type);
    $query_cacheability = new CacheableMetadata();
    $query = $this->getCollectionQuery($resource_type, $query_cacheability);

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
        $cacheability = (new CacheableMetadata())->addCacheContexts(['url.path']);
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
    $collection_data = $this->loadEntitiesWithAccess($storage, $results, $request->get('working_copies_requested', FALSE));
    $primary_data = new ResourceObjectData($collection_data);
    $primary_data->setHasNextPage($has_next_page);

    // Calculate all the results and pass into a WOT:API Data object.
    $count_query_cacheability = new CacheableMetadata();

    $response = $this->respondWithCollection($primary_data, $request, $resource_type);

    $response->addCacheableDependency($query_cacheability);
    $response->addCacheableDependency($count_query_cacheability);

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
    $response = $this->buildWrappedResponse($primary_data, $request);

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

    $response = $this->buildWrappedResponse($field_list, $request, $response_code, [], array_reduce(array_keys($relationship_object_urls), function (LinkCollection $links, $key) use ($relationship_object_urls) {
      return $links->withLink($key, new Link(new CacheableMetadata(), $relationship_object_urls[$key], [$key]));
    }, new LinkCollection([])));
    // Add the host entity as a cacheable dependency.
    $response->addCacheableDependency($entity);
    return $response;
  }

  /**
   * Gets a basic query for a collection.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The base WOT:API resource type for the query.
   * @param \Drupal\Core\Cache\CacheableMetadata $query_cacheability
   *   Collects cacheability for the query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   A new query.
   */
  protected function getCollectionQuery(ResourceType $resource_type,CacheableMetadata $query_cacheability) {
    $entity_type = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId());
    $entity_storage = $this->entityTypeManager->getStorage($resource_type->getEntityTypeId());

    $query = $entity_storage->getQuery();

    // Ensure that access checking is performed on the query.
    $query->accessCheck(TRUE);

    $query->addMetaData('pager_size', (int) 50);

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

  /**
   * Builds a response with the appropriate wrapped document.
   *
   * @param mixed $data
   *   The data to wrap.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
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
  protected function buildWrappedResponse($data, Request $request, $response_code = 200, array $headers = [], LinkCollection $links = NULL) {
    assert($data instanceof Data || $data instanceof FieldItemListInterface);
    $links = ($links ? $links: new LinkCollection([]));
    $response = new ResourceResponse(new WotApiDocumentTopLevel($data, $links), $response_code, $headers);
    return $response;
  }

  /**
   * Respond with an entity collection.
   *
   * @param \Drupal\wotapi\WotApiResource\ResourceObjectData $primary_data
   *   The collection of entities.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The base WOT:API resource type for the request to be served.
   *
   * @return \Drupal\wotapi\ResourceResponse
   *   The response.
   */
  protected function respondWithCollection(ResourceObjectData $primary_data, Request $request, ResourceType $resource_type ) {

    $collection_links = new LinkCollection([]);
    $response = $this->buildWrappedResponse($primary_data, $request,200, [], $collection_links);

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
   * @param array $link_context
   *   An associative array with extra data to build the links.
   *
   * @return \Drupal\wotapi\WotApiResource\LinkCollection
   *   An LinkCollection, with:
   *   - a 'next' key if it is not the last page;
   *   - 'prev' and 'first' keys if it's not the first page.
   */
  protected static function getPagerLinks(Request $request,  array $link_context = []) {
    $pager_links = new LinkCollection([]);
    if (!empty($link_context['total_count']) && !$total = (int) $link_context['total_count']) {
      return $pager_links;
    }

    return $pager_links;
  }
}
