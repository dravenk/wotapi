<?php

namespace Drupal\wotapi_action\Exception;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\wotapi_action\Object\Error;
use Drupal\wotapi_action\Object\Response;

/**
 * Custom exception class for the module.
 */
class WotapiActionException extends \Exception implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  /**
   * The JSON-RPC error response for the exception.
   *
   * @var \Drupal\wotapi_action\Object\Response
   *   The RPC response object.
   */
  protected $response;

  /**
   * JsonRpcException constructor.
   *
   * @param \Drupal\wotapi_action\Object\Response $response
   *   The JSON-RPC error response object for the exception.
   * @param \Throwable $previous
   *   The previous exception.
   */
  public function __construct(Response $response, \Throwable $previous = NULL) {
    $this->response = $response;
    $error = $response->getError();
    $this->setCacheability($response);
    parent::__construct($error->getMessage(), $error->getCode(), $previous);
  }

  /**
   * The appropriate JSON-RPC error response for the exception.
   *
   * @return \Drupal\wotapi_action\Object\Response
   *   The RPC response object.
   */
  public function getResponse() {
    return $this->response;
  }

  /**
   * Constructs a JsonRpcException from an arbitrary exception.
   *
   * @param \Throwable|\Exception $previous
   *   An arbitrary exception.
   * @param mixed $id
   *   The request ID, if available.
   *
   * @return static
   */
  public static function fromPrevious($previous, $id = FALSE) {
    if ($previous instanceof WotapiActionException) {
      // Ensures that the ID and version context information are set because it
      // might not have been set or accessible at a lower level.
      $response = $previous->getResponse();
      return static::fromError($response->getError(), $response->id() ?: $id);
    }
    $error = Error::internalError($previous->getMessage());
    $response = static::buildResponse($error, $id);
    return new static($response, $previous);
  }

  /**
   * Constructs a JsonRpcException from an arbitrary error object.
   *
   * @param \Drupal\wotapi_action\Object\Error $error
   *   The error which caused the exception.
   * @param mixed $id
   *   The request ID, if available.
   *
   * @return static
   */
  public static function fromError(Error $error, $id = FALSE) {
    return new static(static::buildResponse($error, $id));
  }

  /**
   * Helper to build a JSON-RPC response object.
   *
   * @param \Drupal\wotapi_action\Object\Error $error
   *   The error object.
   * @param mixed $id
   *   The request ID.
   *
   * @return \Drupal\wotapi_action\Object\Response
   *   The RPC response object.
   */
  protected static function buildResponse(Error $error, $id = FALSE) {
    return new Response($id ? $id : NULL, NULL, $error);
  }

}
