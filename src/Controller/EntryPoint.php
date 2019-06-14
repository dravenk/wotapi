<?php

namespace Drupal\wotapi\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\wotapi\WotApiResource\WotApiDocumentTopLevel;
use Drupal\wotapi\WotApiResource\LinkCollection;
use Drupal\wotapi\WotApiResource\NullIncludedData;
use Drupal\wotapi\WotApiResource\Link;
use Drupal\wotapi\WotApiResource\ResourceObjectData;
use Drupal\wotapi\ResourceResponse;
use Drupal\wotapi\ResourceType\ResourceType;
use Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Controller for the API entry point.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 */
class EntryPoint extends ControllerBase {

  /**
   * The WOT:API resource type repository.
   *
   * @var \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The account object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * EntryPoint constructor.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, AccountInterface $user) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->user = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('wotapi.resource_type.repository'),
      $container->get('current_user')
    );
  }

  /**
   * Controller to list all the resources.
   *
   * @return \Drupal\wotapi\ResourceResponse
   *   The response object.
   */
  public function index() {
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user.roles:authenticated'])
      ->addCacheTags(['wotapi_resource_types']);

    // Only build URLs for exposed resources.
    $resources = array_filter($this->resourceTypeRepository->all(), function ($resource) {
      return !$resource->isInternal();
    });

    $self_link = new Link(new CacheableMetadata(), Url::fromRoute('wotapi.resource_list'), ['self']);

    $urls = array_reduce($resources, function (LinkCollection $carry, ResourceType $resource_type) {
      if ($resource_type->isLocatable() || $resource_type->isMutable()) {
        $route_suffix = $resource_type->isLocatable() ? 'collection' : 'collection.post';
        $url = Url::fromRoute(sprintf('wotapi.%s.%s', $resource_type->getTypeName(), $route_suffix))->setAbsolute();
        // @todo: implement an extension relation type to signal that this is a primary collection resource.
        $link_relation_types = [];
        return $carry->withLink($resource_type->getTypeName(), new Link(new CacheableMetadata(), $url, $link_relation_types));
      }
      return $carry;
    }, new LinkCollection(['self' => $self_link]));

    $meta = [];
//    if ($this->user->isAuthenticated()) {
//      $current_user_uuid = User::load($this->user->id())->uuid();
//      $meta['links']['me'] = ['meta' => ['id' => $current_user_uuid]];
//      $cacheability->addCacheContexts(['user']);
//      try {
//        $me_url = Url::fromRoute(
//          'wotapi.user--user.individual',
//          ['entity' => $current_user_uuid]
//        )
//          ->setAbsolute()
//          ->toString(TRUE);
//        $meta['links']['me']['href'] = $me_url->getGeneratedUrl();
//        // The cacheability of the `me` URL is the cacheability of that URL
//        // itself and the currently authenticated user.
//        $cacheability = $cacheability->merge($me_url);
//      }
//      catch (RouteNotFoundException $e) {
//        // Do not add the link if the route is disabled or marked as internal.
//      }
//    }

    $response = new ResourceResponse(new WotApiDocumentTopLevel(new ResourceObjectData([]),  $urls, $meta));
    return $response->addCacheableDependency($cacheability);
  }

}
