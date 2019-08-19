<?php

namespace Drupal\wotapi_action\Object;

/**
 * Request object to help implement JSON RPC's spec for request objects.
 */
class Request {

  /**
   * The  action id.
   *
   * @var string
   */
  protected $acton;

  /**
   * The request parameters, if any.
   *
   * @var \Drupal\wotapi_action\Object\ParameterBag|null
   */
  protected $params;

  /**
   * A string, number or NULL ID. False when an ID was not provided.
   *
   * @var mixed|false
   */
  protected $id;

  /**
   * Indicates if the request is part of a batch or not.
   *
   * @var bool
   */
  protected $inBatch;

  /**
   * Request constructor.
   * @param string $acton
   *   The RPC service method id.
   * @param bool $in_batch
   *   Indicates if the request is part of a batch or not.
   * @param mixed|false $id
   *   A string, number or NULL ID. FALSE for notification requests.
   * @param \Drupal\wotapi_action\Object\ParameterBag|null $params
   *   The request parameters, if any.
   */
  public function __construct($acton, $in_batch = FALSE, $id = FALSE, ParameterBag $params = NULL) {
//    $this->assertValidRequest( $acton, $id);
    $this->acton = $acton;
    $this->inBatch = $in_batch;
    $this->params = $params;
    $this->id = $id;
  }

  /**
   * Gets the ID.
   *
   * @return bool|false|mixed
   *   The request id.
   */
  public function id() {
    return $this->id;
  }

  /**
   * Gets the action's name.
   *
   * Action request like this:
   * {
   *  "fade": {
   *    "input": {
   *      "field.brightness": 50,
   *      "duration": 2000
   *    }
   *  }
   * }
   *
   * @return string
   *   The name of the method to execute.
   */
  public function getAction() {
    return $this->acton;
  }

  /**
   * Checks if this is a batched request.
   *
   * @return bool
   *   True if it's a batched request.
   */
  public function isInBatch() {
    return $this->inBatch;
  }

  /**
   * Gets a parameter by key.
   *
   * @param string $key
   *   The key.
   *
   * @return mixed|null
   *   The parameter.
   */
  public function getParameter($key) {
    if ($this->hasParams() && ($param_value = $this->getParams()->get($key))) {
      return $param_value;
    }
    return NULL;
  }

  /**
   * Checks if the request has parameters.
   *
   * @return bool
   *   True if it has parameters.
   */
  public function hasParams() {
    return !(is_null($this->params) || $this->params->isEmpty());
  }

  /**
   * Checks if this is a notification request.
   *
   * @return bool
   *   True if it's a notification.
   */
  public function isNotification() {
    return $this->id === FALSE;
  }

}
