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

  /**
   * How to use this method.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The usage text for the method.
   */
  public function getUsage();

  /**
   * Whether the parameters are by-position.
   *
   * @return bool
   *   True if the parameters are positional.
   */
  public function areParamsPositional();

}
