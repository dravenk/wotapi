<?php

namespace Drupal\wotapi_action;

use Drupal\wotapi_action\Object\ParameterBag;

/**
 * Add to methods that can be executed with params.
 */
interface ExecutableWithParamsInterface {

  /**
   * Executes the action with the parameters passed in.
   *
   * @param \Drupal\wotapi_action\Object\ParameterBag $params
   *   The parameters.
   *
   * @return mixed
   *   The result of the execution.
   */
  public function execute(ParameterBag $params);

}
