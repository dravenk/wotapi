<?php

namespace Drupal\wotapi\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\FilterInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Filters routes based on the HTTP method and WOT:API's read-only mode.
 */
class ReadOnlyModeMethodFilter implements FilterInterface {

  /**
   * The decorated method filter.
   *
   * @var \Drupal\Core\Routing\FilterInterface
   */
  protected $inner;

  /**
   * Whether WOT:API's read-only mode is enabled.
   *
   * @var bool
   */
  protected $readOnlyModeIsEnabled;

  /**
   * ReadOnlyModeMethodFilter constructor.
   *
   * @param \Drupal\Core\Routing\FilterInterface $inner
   *   The decorated method filter.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(FilterInterface $inner, ConfigFactoryInterface $config_factory) {
    $this->inner = $inner;
    $this->readOnlyModeIsEnabled = $config_factory->get('wotapi.settings')->get('read_only');
  }

  /**
   * {@inheritdoc}
   */
  public function filter(RouteCollection $collection, Request $request) {
    $all_supported_methods = [];
    foreach ($collection->all() as $name => $route) {
      $all_supported_methods = array_merge($all_supported_methods, $route->getMethods());
    }

    $collection = $this->inner->filter($collection, $request);

    if (!$this->readOnlyModeIsEnabled) {
      return $collection;
    }

    $read_only_methods = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];
    foreach ($collection->all() as $name => $route) {
      if (!$route->hasDefault(Routes::WOT_API_ROUTE_FLAG_KEY)) {
        continue;
      }

      $supported_methods = $route->getMethods();
      assert(count($supported_methods) > 0, 'WOT:API routes always have a method specified.');
      $is_read_only_route = empty(array_diff($supported_methods, $read_only_methods));
      if (!$is_read_only_route) {
        $collection->remove($name);
      }
    }
    if (count($collection)) {
      return $collection;
    }
    throw new MethodNotAllowedHttpException(array_intersect($all_supported_methods, $read_only_methods), sprintf("WOT:API is configured to accept only read operations. Site administrators can configure this at %s.", Url::fromRoute('wotapi.settings')->setAbsolute()->toString(TRUE)->getGeneratedUrl()));
  }

}
