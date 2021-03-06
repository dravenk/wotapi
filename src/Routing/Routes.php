<?php

namespace Drupal\wotapi\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\wotapi\Access\RelationshipFieldAccess;
use Drupal\wotapi\ParamConverter\ResourceTypeConverter;
use Drupal\wotapi\ResourceType\ResourceType;
use Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 */
class Routes implements ContainerInjectionInterface {

  /**
   * The service name for the primary WOT:API controller.
   *
   * All resources except the entrypoint are served by this controller.
   *
   * @var string
   */
  const CONTROLLER_SERVICE_NAME = 'wotapi.entity_resource';

  /**
   * A key with which to flag a route as belonging to the WOT:API module.
   *
   * @var string
   */
  const WOT_API_ROUTE_FLAG_KEY = '_is_wotapi';

  /**
   * The route default key for the route's resource type information.
   *
   * @var string
   */
  const RESOURCE_TYPE_KEY = 'resource_type';

  /**
   * The WOT:API resource type repository.
   *
   * @var \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * List of providers.
   *
   * @var string[]
   */
  protected $providerIds;

  /**
   * The WOT:API base path.
   *
   * @var string
   */
  protected $wotApiBasePath;

  /**
   * Instantiates a Routes object.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The WOT:API resource type repository.
   * @param string[] $authentication_providers
   *   The authentication providers, keyed by ID.
   * @param string $wotapi_base_path
   *   The WOT:API base path.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, array $authentication_providers, $wotapi_base_path) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->providerIds = array_keys($authentication_providers);
    assert(is_string($wotapi_base_path));
    assert(
      $wotapi_base_path[0] === '/',
      sprintf('The provided base path should contain a leading slash "/". Given: "%s".', $wotapi_base_path)
    );
    assert(
      substr($wotapi_base_path, -1) !== '/',
      sprintf('The provided base path should not contain a trailing slash "/". Given: "%s".', $wotapi_base_path)
    );
    $this->wotApiBasePath = $wotapi_base_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('wotapi.resource_type.repository'),
      $container->getParameter('authentication_providers'),
      $container->getParameter('wotapi.base_path')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = new RouteCollection();

    // WOT:API's routes: entry point + routes for every resource type.
    foreach ($this->resourceTypeRepository->all() as $resource_type) {
      $routes->addCollection(static::getRoutesForResourceType($resource_type, $this->wotApiBasePath));
    }

    // Require the WOT:API media type header on every route, except on file
    // upload routes, where we require `application/octet-stream`.
    //    $routes->addRequirements(['_content_type_format' => 'api_json']);.
    // Enable all available authentication providers.
    $routes->addOptions(['_auth' => $this->providerIds]);

    // Flag every route as belonging to the WOT:API module.
    $routes->addDefaults([static::WOT_API_ROUTE_FLAG_KEY => TRUE]);

    // All routes serve only the WOT:API media type.
    $routes->addRequirements(['_format' => 'api_json']);

    return $routes;
  }

  /**
   * Gets applicable resource routes for a WOT:API resource type.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The WOT:API resource type for which to get the routes.
   * @param string $path_prefix
   *   The root path prefix.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   A collection of routes for the given resource type.
   */
  protected static function getRoutesForResourceType(ResourceType $resource_type, $path_prefix) {
    // Internal resources have no routes.
    if ($resource_type->isInternal()) {
      return new RouteCollection();
    }

    $routes = new RouteCollection();

    // Collection route like `/wotapi/node/article`.
    if ($resource_type->isLocatable()) {
      $collection_route = new Route("/{$resource_type->getPath()}");
      $collection_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':getCollection']);
      $collection_route->setMethods(['GET']);
      // Allow anybody access because "view" and "view label" access are checked
      // in the controller.
      $collection_route->setRequirement('_access', 'TRUE');
      $routes->add(static::getRouteName($resource_type, 'collection'), $collection_route);
    }

    // Individual routes like `/wotapi/node/article/{uuid}` or
    // `/wotapi/node/article/{uuid}/properties/uid`.
    $routes->addCollection(static::getIndividualRoutesForResourceType($resource_type));

    // Add the resource type as a parameter to every resource route.
    foreach ($routes as $route) {
      static::addRouteParameter($route, static::RESOURCE_TYPE_KEY, ['type' => ResourceTypeConverter::PARAM_TYPE_ID]);
      $route->addDefaults([static::RESOURCE_TYPE_KEY => $resource_type->getTypeName()]);
    }

    // Resource routes all have the same base path.
    $routes->addPrefix($path_prefix);

    return $routes;
  }

  /**
   * Determines if the given request is for a WOT:API generated route.
   *
   * @param array $defaults
   *   The request's route defaults.
   *
   * @return bool
   *   Whether the request targets a generated route.
   */
  public static function isWotApiRequest(array $defaults) {
    return isset($defaults[RouteObjectInterface::CONTROLLER_NAME])
      && strpos($defaults[RouteObjectInterface::CONTROLLER_NAME], static::CONTROLLER_SERVICE_NAME) === 0;
  }

  /**
   * Gets a route collection for the given resource type.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The resource type for which the route collection should be created.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The route collection.
   */
  protected static function getIndividualRoutesForResourceType(ResourceType $resource_type) {
    if (!$resource_type->isLocatable()) {
      return new RouteCollection();
    }

    $routes = new RouteCollection();

    $path = $resource_type->getPath();
    $entity_type_id = $resource_type->getEntityTypeId();

    // Individual read, update and remove.
    $individual_route = new Route("/{$path}/{entity}");
    $individual_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':getIndividual']);
    $individual_route->setMethods(['GET']);
    // No _entity_access requirement because "view" and "view label" access are
    // checked in the controller. So it's safe to allow anybody access.
    $individual_route->setRequirement('_access', 'TRUE');
    $routes->add(static::getRouteName($resource_type, 'individual'), $individual_route);

    foreach ($resource_type->getRelatableResourceTypes() as $relationship_field_name => $target_resource_types) {
      // Only create routes for related routes that target at least one
      // non-internal resource type.
      if (static::hasNonInternalTargetResourceTypes($target_resource_types)) {
        // Get an individual resource's related resources.
        $related_route = new Route("/{$path}/{entity}/properties/{$relationship_field_name}");
        $related_route->setMethods(['GET']);
        $related_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':getRelated']);
        $related_route->addDefaults(['related' => $relationship_field_name]);
        $related_route->setRequirement(RelationshipFieldAccess::ROUTE_REQUIREMENT_KEY, $relationship_field_name);
        $routes->add(static::getRouteName($resource_type, "$relationship_field_name.related"), $related_route);
      }
    }

    $properties_route = new Route("/{$path}/{entity}/properties");
    $properties_route->setMethods(['GET']);
    $properties_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':getThingProperties']);
    $properties_route->setRequirement('_access', 'TRUE');
    $routes->add(static::getRouteName($resource_type, "properties"), $properties_route);

    $actions_route = new Route("/{$path}/{entity}/actions");
    $actions_route->setMethods(['GET']);
    $actions_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':getThingActions']);
    $actions_route->setRequirement('_access', 'TRUE');
    $routes->add(static::getRouteName($resource_type, "actions"), $actions_route);

    $actions_post_route = clone $actions_route;
    $actions_post_route->setMethods(['POST']);
    $actions_post_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':postThingActions']);
    $routes->add(static::getRouteName($resource_type, "actions.post"), $actions_post_route);

    // Add entity parameter conversion to every route.
    $routes->addOptions(['parameters' => ['entity' => ['type' => 'entity:' . $entity_type_id]]]);

    return $routes;
  }

  /**
   * Adds a parameter option to a route, overrides options of the same name.
   *
   * The Symfony Route class only has a method for adding options which
   * overrides any previous values. Therefore, it is tedious to add a single
   * parameter while keeping those that are already set.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to which the parameter is to be added.
   * @param string $name
   *   The name of the parameter.
   * @param mixed $parameter
   *   The parameter's options.
   */
  protected static function addRouteParameter(Route $route, $name, $parameter) {
    $parameters = $route->getOption('parameters') ?: [];
    $parameters[$name] = $parameter;
    $route->setOption('parameters', $parameters);
  }

  /**
   * Get a unique route name for the WOT:API resource type and route type.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The resource type for which the route collection should be created.
   * @param string $route_type
   *   The route type. E.g. 'individual' or 'collection'.
   *
   * @return string
   *   The generated route name.
   */
  public static function getRouteName(ResourceType $resource_type, $route_type) {
    return sprintf('wotapi.%s.%s', $resource_type->getTypeName(), $route_type);
  }

  /**
   * Determines if an array of resource types has any non-internal ones.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType[] $resource_types
   *   The resource types to check.
   *
   * @return bool
   *   TRUE if there is at least one non-internal resource type in the given
   *   array; FALSE otherwise.
   */
  protected static function hasNonInternalTargetResourceTypes(array $resource_types) {
    return array_reduce($resource_types, function ($carry, ResourceType $target) {
      return $carry || !$target->isInternal();
    }, FALSE);
  }

  /**
   * Determines if an array of resource types lists non-internal "file" ones.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType[] $resource_types
   *   The resource types to check.
   *
   * @return bool
   *   TRUE if there is at least one non-internal "file" resource type in the
   *   given array; FALSE otherwise.
   */
  protected static function hasNonInternalFileTargetResourceTypes(array $resource_types) {
    return array_reduce($resource_types, function ($carry, ResourceType $target) {
      return $carry || (!$target->isInternal() && $target->getEntityTypeId() === 'file');
    }, FALSE);
  }

  /**
   * Gets the resource type from a route or request's parameters.
   *
   * @param array $parameters
   *   An array of parameters. These may be obtained from a route's
   *   parameter defaults or from a request object.
   *
   * @return \Drupal\wotapi\ResourceType\ResourceType|null
   *   The resource type, NULL if one cannot be found from the given parameters.
   */
  public static function getResourceTypeNameFromParameters(array $parameters) {
    if (isset($parameters[static::WOT_API_ROUTE_FLAG_KEY]) && $parameters[static::WOT_API_ROUTE_FLAG_KEY]) {
      return isset($parameters[static::RESOURCE_TYPE_KEY]) ? $parameters[static::RESOURCE_TYPE_KEY] : NULL;
    }
    return NULL;
  }

  /**
   * Invalidates any WOT:API resource type dependent responses and routes.
   */
  public static function rebuild() {
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['wotapi_resource_types']);
    \Drupal::service('router.builder')->setRebuildNeeded();
  }

}
