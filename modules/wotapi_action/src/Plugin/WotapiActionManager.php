<?php

namespace Drupal\wotapi_action\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\wotapi_action\Annotation\WotapiAction;

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
    parent::alterDefinitions($definitions);
  }

}
