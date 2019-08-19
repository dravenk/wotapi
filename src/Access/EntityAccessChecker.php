<?php

namespace Drupal\wotapi\Access;

use Drupal\content_moderation\Access\LatestRevisionCheck;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\wotapi\Exception\EntityAccessDeniedHttpException;
use Drupal\wotapi\WotApiResource\LabelOnlyResourceObject;
use Drupal\wotapi\WotApiResource\ResourceObject;
use Drupal\wotapi\WotApiSpec;
use Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\Access\NodeRevisionAccessCheck;
use Symfony\Component\Routing\RouterInterface;

/**
 * Checks access to entities.
 *
 * WOT:API needs to check access to every single entity type. Some entity types
 * have non-standard access checking logic. This class centralizes entity access
 * checking logic.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 */
class EntityAccessChecker {

  /**
   * The WOT:API resource type repository.
   *
   * @var \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The router.
   *
   * @var \Symfony\Component\Routing\RouterInterface
   */
  protected $router;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The node revision access check service.
   *
   * This will be NULL unless the node module is installed.
   *
   * @var \Drupal\node\Access\NodeRevisionAccessCheck|null
   */
  protected $nodeRevisionAccessCheck = NULL;

  /**
   * The latest revision check service.
   *
   * This will be NULL unless the content_moderation module is installed. This
   * is a temporary measure. WOT:API should not need to be aware of the
   * Content Moderation module.
   *
   * @var \Drupal\content_moderation\Access\LatestRevisionCheck
   */
  protected $latestRevisionCheck = NULL;

  /**
   * EntityAccessChecker constructor.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The WOT:API resource type repository.
   * @param \Symfony\Component\Routing\RouterInterface $router
   *   The router.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, RouterInterface $router, AccountInterface $account, EntityRepositoryInterface $entity_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->router = $router;
    $this->currentUser = $account;
    $this->entityRepository = $entity_repository;
  }

  /**
   * Sets the node revision access check service.
   *
   * This is only called when node module is installed.
   *
   * @param \Drupal\node\Access\NodeRevisionAccessCheck $node_revision_access_check
   *   The node revision access check service.
   */
  public function setNodeRevisionAccessCheck(NodeRevisionAccessCheck $node_revision_access_check) {
    $this->nodeRevisionAccessCheck = $node_revision_access_check;
  }


  /**
   * Sets the media revision access check service.
   *
   * This is only called when content_moderation module is installed.
   *
   * @param \Drupal\content_moderation\Access\LatestRevisionCheck $latest_revision_check
   *   The latest revision access check service provided by the
   *   content_moderation module.
   *
   * @see self::$latestRevisionCheck
   */
  public function setLatestRevisionCheck(LatestRevisionCheck $latest_revision_check) {
    $this->latestRevisionCheck = $latest_revision_check;
  }

  /**
   * Get the object to normalize and the access based on the provided entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to test access for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The account with which access should be checked. Defaults to
   *   the current user.
   *
   * @return \Drupal\wotapi\WotApiResource\ResourceObject|\Drupal\wotapi\WotApiResource\LabelOnlyResourceObject|\Drupal\wotapi\Exception\EntityAccessDeniedHttpException
   *   The ResourceObject, a LabelOnlyResourceObject or an
   *   EntityAccessDeniedHttpException object if neither is accessible. All
   *   three possible return values carry the access result cacheability.
   */
  public function getAccessCheckedResourceObject(EntityInterface $entity, AccountInterface $account = NULL) {
    $account = $account ?: $this->currentUser;
    $resource_type = $this->resourceTypeRepository->get($entity->getEntityTypeId(), $entity->bundle());
    $entity = $this->entityRepository->getTranslationFromContext($entity, NULL, ['operation' => 'entity_upcast']);
    $access = $this->checkEntityAccess($entity, 'view', $account);
    $entity->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      // If this is the default revision or the entity is not revisionable, then
      // check access to the entity label. Revision support is all or nothing.
      if (!$entity->getEntityType()->isRevisionable()) {
        $label_access = $entity->access('view label', NULL, TRUE);
        $entity->addCacheableDependency($label_access);
        if ($label_access->isAllowed()) {
          return LabelOnlyResourceObject::createFromEntity($resource_type, $entity);
        }
        $access = $access->orIf($label_access);
      }
      return new EntityAccessDeniedHttpException($entity, $access, '/data', 'The current user is not allowed to GET the selected resource.');
    }
    return ResourceObject::createFromEntity($resource_type, $entity);
  }

  /**
   * Checks access to the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which access should be evaluated.
   * @param string $operation
   *   The entity operation for which access should be evaluated.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The account with which access should be checked. Defaults to
   *   the current user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|\Drupal\Core\Access\AccessResultReasonInterface
   *   The access check result.
   */
  public function checkEntityAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $access = $entity->access($operation, $account, TRUE);
    if ($entity->getEntityType()->isRevisionable()) {
      $access = AccessResult::neutral()->addCacheContexts(['url.query_args:' . WotApiSpec::VERSION_QUERY_PARAMETER])->orIf($access);
    }
    return $access;
  }

}
