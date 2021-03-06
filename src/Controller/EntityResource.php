<?php

namespace Drupal\wotapi\Controller;

use Drupal\wotapi_action\Object\Request as ActionRequest;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
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
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Process all entity requests.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
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

    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    // Each item of the collection data contains an array with 'entity' and
    // 'access' elements.
    $ids = \Drupal::entityQuery($entity_type_id)->execute();
    $collection_data = $this->loadEntitiesWithAccess($storage, $ids, $request->get('working_copies_requested', FALSE));
    $primary_data = new ResourceObjectData($collection_data);

    // Calculate all the results and pass into a WOT:API Data object.
    $count_query_cacheability = new CacheableMetadata();

    $response = $this->respondWithCollection($primary_data, $request, $resource_type);

    $response->addCacheableDependency($count_query_cacheability);

    return $response;
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
      $property = $this->entityAccessChecker->getAccessCheckedResourceObject($referenced_entity);
      $property->setSourceField($entity->get($related));
      $collection_data[] = $property;
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
    $relationship_object_urls = PropertiesFieldNormalizer::getPropertiesLinks($resource_object, $related);

    $response = $this->buildWrappedResponse($field_list, $request, $response_code, [], array_reduce(array_keys($relationship_object_urls), function (LinkCollection $links, $key) use ($relationship_object_urls) {
      return $links->withLink($key, new Link(new CacheableMetadata(), $relationship_object_urls[$key], [$key]));
    }, new LinkCollection([])));
    // Add the host entity as a cacheable dependency.
    $response->addCacheableDependency($entity);
    return $response;
  }

  /**
   * Gets the relationship of an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The requested entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\wotapi\ResourceResponse
   *   The response.
   */
  public function getThingProperties(FieldableEntityInterface $entity, Request $request) {
    $resource_object = $this->entityAccessChecker->getAccessCheckedResourceObject($entity);
    if ($resource_object instanceof EntityAccessDeniedHttpException) {
      throw $resource_object;
    }
    $collection_data = [];
    $fields = $resource_object->getFields();
    foreach ($fields as $field) {
      /* @var $field \Drupal\Core\Field\FieldItemList */
      $field_target = $field->getSetting('target_type');
      if ($field_target == 'wotapi_property') {
        $entities = $field->referencedEntities();
        foreach ($entities as $referenced_entity) {
          $property = $this->entityAccessChecker->getAccessCheckedResourceObject($referenced_entity);
          $property->setSourceField($field);
          $collection_data[] = $property;
        }
      }
    }
    $primary_data = new ResourceObjectData($collection_data, -1);
    $response = $this->buildWrappedResponse($primary_data, $request);
    // $response does not contain the entity list cache tag. We add the
    // cacheable metadata for the finite list of entities in the relationship.
    $response->addCacheableDependency($entity);
    return $response;
  }

  /**
   * Gets the relationship of an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The requested entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\wotapi\ResourceResponse
   *   The response.
   */
  public function getThingActions(FieldableEntityInterface $entity, Request $request) {
    $resource_object = $this->entityAccessChecker->getAccessCheckedResourceObject($entity);
    if ($resource_object instanceof EntityAccessDeniedHttpException) {
      throw $resource_object;
    }
    $collection_data = [];
    // $fields = $resource_object->getFields();
    //    foreach ($fields as $field_name => $field) {
    //      if ($field->getFieldDefinition()->getType() == 'wotapi_action') {
    //        foreach ($field->getValue() as $key => $value) {
    //          $action_id = array_values($value)[0];
    //          $action = \Drupal::service('wotapi_action.handler')->getAction($action_id);
    //          $collection_data[] =  $action;
    //        }
    //      }
    //    }
    $primary_data = new ResourceObjectData($collection_data);
    $response = $this->buildWrappedResponse($primary_data, $request);
    // $response does not contain the entity list cache tag. We add the
    // cacheable metadata for the finite list of entities in the relationship.
    $response->addCacheableDependency($entity);
    return $response;
  }

  /**
   * Gets the relationship of an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The requested entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\wotapi\ResourceResponse
   *   The response.
   */
  public function postThingActions(FieldableEntityInterface $entity, Request $request) {
    $content = Json::decode($request->getContent(FALSE));
    foreach ($content as $action_id => $value) {
      $action_handler = \Drupal::service('wotapi_action.handler');
      $action_request = new ActionRequest($action_id, FALSE, FALSE, NULL);
      $action_responses = $action_handler->batch([$action_request]);
    }
    //
    //    $response = $this->buildWrappedResponse($entity, $request);
    //    $response->addCacheableDependency($entity);
    //    return $response;
    return new ResourceResponse();
  }

  /**
   * Loads the entity targeted by a resource identifier.
   *
   * @param \Drupal\wotapi\WotApiResource\ResourceIdentifier $resource_identifier
   *   A resource identifier.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity targeted by a resource identifier.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException | \Drupal\Core\Entity\EntityStorageException
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
    $links = ($links ? $links : new LinkCollection([]));
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
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function respondWithCollection(ResourceObjectData $primary_data, Request $request, ResourceType $resource_type) {

    $collection_links = new LinkCollection([]);
    $response = $this->buildWrappedResponse($primary_data, $request, 200, [], $collection_links);

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
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function entityExists(EntityInterface $entity) {
    $entity_storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    return !empty($entity_storage->loadByProperties([
      'uuid' => $entity->uuid(),
    ]));
  }

}
