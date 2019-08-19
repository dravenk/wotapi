<?php

namespace Drupal\wotapi\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter for upcasting WOT:API resource type names to objects.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 */
class ResourceTypeConverter implements ParamConverterInterface {

  /**
   * The route parameter type to match.
   *
   * @var string
   */
  const PARAM_TYPE_ID = 'wotapi_resource_type';

  /**
   * The WOT:API resource type repository.
   *
   * @var \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * ResourceTypeConverter constructor.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The WOT:API resource type repository.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    return $this->resourceTypeRepository->getByTypeName($value);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return (!empty($definition['type']) && $definition['type'] === static::PARAM_TYPE_ID);
  }

}
