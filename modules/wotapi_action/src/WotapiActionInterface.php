<?php

namespace Drupal\wotapi_action;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Core\Access\AccessibleInterface;

/**
 * Interface for the method plugins.
 */
interface WotapiActionInterface extends AccessibleInterface, PluginDefinitionInterface {

  /**
   * The class method to call.
   *
   * @return string
   *   The PHP method on the RPC method object to call. Defaults to: execute.
   */
  public function call();

}
