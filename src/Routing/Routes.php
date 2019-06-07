<?php

namespace Drupal\wotapi\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\wotapi\ParamConverter\ResourceTypeConverter;
use Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\wotapi\Controller\EntryPoint;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\wotapi\ResourceType\ResourceType;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
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
   * The JSON:API resource type repository.
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
   * The WOT:API base path.
   *
   * @var string
   */
  protected $wotApiBasePath;

  /**
   * Instantiates a Routes object.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param string[] $authentication_providers
   *   The authentication providers, keyed by ID.
   * @param string $wotapi_base_path
   *   The JSON:API base path.
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
    $routes->add('wotapi.resource_list', static::getEntryPointRoute($this->wotApiBasePath));

    // Enable all available authentication providers.
    $routes->addOptions(['_auth' => $this->providerIds]);

    // Flag every route as belonging to the JSON:API module.
    $routes->addDefaults([static::WOT_API_ROUTE_FLAG_KEY => TRUE]);

    // All routes serve only the JSON:API media type.
    $routes->addRequirements(['_format' => 'api_json']);

    return $routes;
  }

  /**
   * Gets applicable resource routes for a JSON:API resource type.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type for which to get the routes.
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
//
//    // Creation route.
//    if ($resource_type->isMutable()) {
//      $collection_create_route = new Route("/{$resource_type->getPath()}");
//      $collection_create_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':createIndividual']);
//      $collection_create_route->setMethods(['POST']);
//      $create_requirement = sprintf("%s:%s", $resource_type->getEntityTypeId(), $resource_type->getBundle());
//      $collection_create_route->setRequirement('_entity_create_access', $create_requirement);
//      $collection_create_route->setRequirement('_csrf_request_header_token', 'TRUE');
//      $routes->add(static::getRouteName($resource_type, 'collection.post'), $collection_create_route);
//    }

//    // Individual routes like `/wotapi/node/article/{uuid}` or
//    // `/wotapi/node/article/{uuid}/relationships/uid`.
//    $routes->addCollection(static::getIndividualRoutesForResourceType($resource_type));
//
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
   * Get a unique route name for the JSON:API resource type and route type.
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
   * Determines if the given request is for a JSON:API generated route.
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
   * Provides the entry point route.
   *
   * @param string $path_prefix
   *   The root path prefix.
   *
   * @return \Symfony\Component\Routing\Route
   *   The entry point route.
   */
  protected function getEntryPointRoute($path_prefix) {
    $entry_point = new Route("/{$path_prefix}");
    $entry_point->addDefaults([RouteObjectInterface::CONTROLLER_NAME => EntryPoint::class . '::index']);
    $entry_point->setRequirement('_access', 'TRUE');
    $entry_point->setMethods(['GET']);
    return $entry_point;
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
}
