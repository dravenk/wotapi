<?php

namespace Drupal\wotapi_action\Plugin;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\wotapi_action\Annotation\WotapiAction;
use Drupal\wotapi_action\WotapiActionInterface;
use Drupal\wotapi_action\ParameterFactoryInterface;

/**
 * Provides the WotapiAction plugin plugin manager.
 *
 * @internal
 */
class WotapiActionManager extends DefaultPluginManager {

  /**
   * Constructs a new HookPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->alterInfo(FALSE);
    parent::__construct('Plugin/wotapi_action/Action', $namespaces, $module_handler, NULL, WotapiAction::class);
    $this->setCacheBackend($cache_backend, 'wotapi_action_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function alterDefinitions(&$definitions) {
    /* @var \Drupal\wotapi_action\Annotation\WotapiAction $method */
    foreach ($definitions as &$method) {
      $this->assertValidJsonRpcMethodPlugin($method);
      if (isset($method->params)) {
        foreach ($method->params as $key => &$param) {
          $param->setId($key);
        }
      }
    }
    parent::alterDefinitions($definitions);
  }

  /**
   * Asserts that the plugin class is valid.
   *
   * @param \Drupal\wotapi_action\WotapiActionInterface $method
   *   The JSON-RPC method definition.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function assertValidJsonRpcMethodPlugin(WotapiActionInterface $method) {
    foreach ($method->params as $param) {
      if (!$param->factory && !$param->schema) {
        throw new InvalidPluginDefinitionException($method->id(), "Every JsonRpcParameterDefinition must define either a factory or a schema.");
      }
      if ($param->factory && !is_subclass_of($param->factory, ParameterFactoryInterface::class)) {
        throw new InvalidPluginDefinitionException($method->id(), "Parameter factories must implement ParameterFactoryInterface.");
      }
    }
  }

}
