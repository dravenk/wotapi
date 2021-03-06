<?php

namespace Drupal\wotapi\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\wotapi\HandlerInterface;
use Drupal\wotapi\WotapiActionInterface;
use Drupal\wotapi\Normalizer\AnnotationNormalizer;
use Drupal\serialization\Normalizer\NormalizerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The controller that responds with the discovery information.
 */
class DiscoveryController extends ControllerBase {

  /**
   * The JSON-RPC handler.
   *
   * @var \Drupal\wotapi\HandlerInterface
   */
  protected $handler;

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * DiscoveryController constructor.
   */
  public function __construct(HandlerInterface $handler, SerializerInterface $serializer) {
    $this->handler = $handler;
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('wotapi_action.handler'), $container->get('serializer'));
  }

  /**
   * List the available methods.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The response object.
   */
  public function actions() {
    $cacheability = new CacheableMetadata();
    $methods = array_values($this->getAvailableActions($cacheability));
    $serialized = $this->serializer->serialize($methods, 'json', [
      AnnotationNormalizer::DEPTH_KEY => 0,
      NormalizerBase::SERIALIZATION_CONTEXT_CACHEABILITY => $cacheability,
    ]);
    return CacheableJsonResponse::fromJsonString($serialized)->addCacheableDependency($cacheability);
  }

  /**
   * Gets all accessible methods for the RPC handler.
   *
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   The cacheability information for the current request.
   *
   * @return \Drupal\wotapi\WotapiActionInterface[]
   *   The methods.
   */
  protected function getAvailableActions(RefinableCacheableDependencyInterface $cacheability) {
    return array_filter($this->handler->supportedActions(), function (WotapiActionInterface $method) use ($cacheability) {
      $access_result = $method->access('view', NULL, TRUE);
      $cacheability->addCacheableDependency($access_result);
      return $access_result->isAllowed();
    });
  }

}
