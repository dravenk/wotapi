<?php

namespace Drupal\wotapi_action\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\wotapi_action\Exception\WotapiActionException;
use Drupal\wotapi_action\Shaper\RpcRequestFactory;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The main front controller.
 *
 * Handles all the incoming requests HTTP requests and responses.
 */
class HttpController extends ControllerBase {

  /**
   * The RPC handler service.
   *
   * @var \Drupal\wotapi_action\HandlerInterface
   */
  protected $handler;

  /**
   * The JSON Schema validator service.
   *
   * @var \JsonSchema\Validator
   */
  protected $validator;

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * HttpController constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   */
  public function __construct(ContainerInterface $container) {
    $this->handler = $container->get('wotapi_action.handler');
    $this->validator = $container->get('wotapi_action.schema_validator');
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container);
  }

  /**
   * Resolves an RPC request over HTTP.
   *
   * @param \Symfony\Component\HttpFoundation\Request $http_request
   *   The HTTP request.
   *
   * @return \Drupal\Core\Cache\CacheableResponseInterface
   *   The HTTP response.
   */
  public function resolve(Request $http_request) {
    // Map the HTTP request to an RPC request.
    try {
      $rpc_requests = $this->getRpcRequests($http_request);
    }
    catch (WotapiActionException $e) {
      return $this->exceptionResponse($e, Response::HTTP_BAD_REQUEST);
    }

    // Execute the RPC request and get the RPC response.
    try {
      $rpc_responses = $this->getRpcResponses($rpc_requests);

      // If no RPC response(s) were generated (happens if all of the request(s)
      // were notifications), then return a 204 HTTP response.
      if (empty($rpc_responses)) {
        return CacheableJsonResponse::create(NULL, Response::HTTP_NO_CONTENT);
      }

      // Map the RPC response(s) to an HTTP response.
      //      $is_batched_response = count($rpc_requests) !== 1 || $rpc_requests[0]->isInBatch();
      return $this->getHttpResponse($rpc_responses, TRUE);
    }
    catch (WotapiActionException $e) {
      return $this->exceptionResponse($e, Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Get the JSON RPC request objects for the given Request object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $http_request
   *   The HTTP request.
   *
   * @return \Drupal\wotapi_action\Object\Request[]
   *   The JSON-RPC request or requests.
   *
   * @throws \Drupal\wotapi_action\Exception\WotapiActionException
   *   When there was an error handling the response.
   */
  protected function getRpcRequests(Request $http_request) {
    try {
      if ($http_request->getMethod() === Request::METHOD_POST) {
        $content = Json::decode($http_request->getContent(FALSE));
      }
      elseif ($http_request->getMethod() === Request::METHOD_GET) {
        $content = Json::decode($http_request->query->get('query'));
      }
      $context = new Context([]);
      $factory = new RpcRequestFactory($this->handler, $this->container, $this->validator);
      return $factory->transform($content, $context);
    }
    catch (\Exception $e) {
      $id = (isset($content) && is_object($content) && isset($content->id)) ? $content->id : FALSE;
      throw WotapiActionException::fromPrevious($e, $id);
    }
  }

  /**
   * Get the JSON RPC request objects for the given JSON RPC request objects.
   *
   * @param \Drupal\wotapi_action\Object\Request[] $rpc_requests
   *   The RPC request objects.
   *
   * @return \Drupal\wotapi_action\Object\Response[]|null
   *   The JSON-RPC response(s). NULL when the RPC request contains only
   *   notifications.
   *
   * @throws \Drupal\wotapi_action\Exception\WotapiActionException
   */
  protected function getRpcResponses(array $rpc_requests) {
    $rpc_responses = $this->handler->batch($rpc_requests);
    return empty($rpc_responses)
      ? NULL
      : $rpc_responses;
  }

  /**
   * Map RPC response(s) to an HTTP response.
   *
   * @param \Drupal\wotapi_action\Object\Response[] $rpc_responses
   *   The RPC responses.
   * @param bool $is_batched_response
   *   True if the response is batched.
   *
   * @return \Drupal\Core\Cache\CacheableResponseInterface
   *   The cacheable HTTP version of the RPC response(s).
   *
   * @throws \Drupal\wotapi_action\Exception\WotapiActionException
   */
  protected function getHttpResponse(array $rpc_responses, $is_batched_response) {
    try {
      $serialized = $this->serializeRpcResponse($rpc_responses, $is_batched_response);
      $http_response = CacheableJsonResponse::fromJsonString($serialized, Response::HTTP_OK);
      // Varies the response based on the 'query' parameter.
      $cache_context = (new CacheableMetadata())
        ->setCacheContexts(['url.query_args:query']);
      $http_response->addCacheableDependency($cache_context);
      // Adds the cacheability information of the RPC response(s) to the HTTP
      // response.
      return array_reduce($rpc_responses, function (CacheableResponseInterface $http_response, $response) {
        return $http_response->addCacheableDependency($response);
      }, $http_response);
    }
    catch (\Exception $e) {
      throw WotapiActionException::fromPrevious($e, FALSE);
    }
  }

  /**
   * Serializes the RPC response object into JSON.
   *
   * @param \Drupal\wotapi_action\Object\Response[] $rpc_responses
   *   The response objects.
   * @param bool $is_batched_response
   *   True if this is a batched response.
   *
   * @return string
   *   The serialized JSON-RPC response body.
   */
  protected function serializeRpcResponse(array $rpc_responses, $is_batched_response) {
    // This following is needed to prevent the serializer from using array
    // indices as JSON object keys like {"0": "foo", "1": "bar"}.
    $data = array_values($rpc_responses);
    // $normalizer = $this->validator;
    //    return Json::encode($normalizer->transform($data, $context));
    return Json::encode($data);
  }

  /**
   * Generates the expected response for a given exception.
   *
   * @param \Drupal\wotapi_action\Exception\WotapiActionException $e
   *   The exception that generates the error response.
   * @param int $status
   *   The response HTTP status.
   *
   * @return \Drupal\Core\Cache\CacheableResponseInterface
   *   The response object.
   */
  protected function exceptionResponse(WotapiActionException $e, $status = Response::HTTP_INTERNAL_SERVER_ERROR) {
    $context = new Context([
      RpcRequestFactory::REQUEST_IS_BATCH_REQUEST => FALSE,
    ]);
    $normalizer = $this->validator;
    $rpc_response = $e->getResponse();
    $serialized = Json::encode($normalizer->transform([$rpc_response], $context));
    $response = CacheableJsonResponse::fromJsonString($serialized, $status);
    return $response->addCacheableDependency($rpc_response);
  }

}
