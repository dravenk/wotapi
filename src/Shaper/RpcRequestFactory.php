<?php

namespace Drupal\wotapi\Shaper;

use Drupal\Component\Serialization\Json;
use Drupal\wotapi\Exception\WotapiActionException;
use Drupal\wotapi\HandlerInterface;
use Drupal\wotapi\Object\Error;
use Drupal\wotapi\Object\Request;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Shaper\Transformation\TransformationBase;
use Shaper\Util\Context;
use Shaper\Validator\CollectionOfValidators;
use Shaper\Validator\InstanceofValidator;
use Shaper\Validator\JsonSchemaValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates RPC Request objects.
 */
class RpcRequestFactory extends TransformationBase {

  const REQUEST_ID_KEY = 'jsonrpc_request_id';

  const REQUEST_IS_BATCH_REQUEST = 'jsonrpc_request_is_batch_request';

  /**
   * The JSON-RPC handler.
   *
   * @var \Drupal\wotapi\HandlerInterface
   */
  protected $handler;

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The JSON Schema validator instance.
   *
   * @var \JsonSchema\Validator
   */
  protected $validator;

  /**
   * {@inheritdoc}
   */
  public function __construct(HandlerInterface $handler, ContainerInterface $container, Validator $validator) {
    $this->handler = $handler;
    $this->container = $container;
    $this->validator = $validator;
  }

  /**
   * {@inheritdoc}
   */
  public function transform($data, Context $context = NULL) {
    if (!isset($context)) {
      $context = new Context();
    }
    return $this->doTransform($data, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputValidator() {
    $schema = Json::decode(file_get_contents(__DIR__ . '/request-schema.json'));
    return new JsonSchemaValidator($schema, $this->validator, Constraint::CHECK_MODE_TYPE_CAST);
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputValidator() {
    return new CollectionOfValidators(new InstanceofValidator(Request::class));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\wotapi\Exception\WotapiActionException
   */
  protected function doTransform($data, Context $context) {
    // $context[static::REQUEST_IS_BATCH_REQUEST] = $this->isBatchRequest($data);
    //    // Treat everything as a batch of requests for uniformity.
    //    $data = $this->isBatchRequest($data) ? $data : [$data];
    return array_map(function ($item) use ($context) {
      return $this->denormalizeRequest($item, $context);
    }, $data);
  }

  /**
   * Denormalizes a single JSON-RPC request object.
   *
   * @param object $data
   *   The decoded JSON-RPC request to be denormalized.
   * @param \Shaper\Util\Context $context
   *   The denormalized JSON-RPC request.
   *
   * @return \Drupal\wotapi\Object\Request
   *   The JSON-RPC request.
   *
   * @throws \Drupal\wotapi\Exception\WotapiActionException
   */
  protected function denormalizeRequest($data, Context $context) {
    $id = isset($data['id']) ? $data['id'] : FALSE;
    $context[static::REQUEST_ID_KEY] = $id;
    $batch = $context[static::REQUEST_IS_BATCH_REQUEST];
    // Return new Request($data['action'], $batch, $id, NULL);.
    return new Request($data, $batch, $id, NULL);
  }

  /**
   * Determine if the request is a batch request.
   *
   * @param array $data
   *   The raw HTTP request data.
   *
   * @return bool
   *   Whether the HTTP request contains more than one RPC request.
   *
   * @throws \Drupal\wotapi\Exception\WotapiActionException
   *   Thrown if the request contains RPC requests without a 'wotapi_action' member.
   */
  protected function isBatchRequest(array $data) {
    if (isset($data['wotapi_action'])) {
      return FALSE;
    }
    // $supported_version = $this->handler->supportedVersion();
    //    $filter = function ($version) use ($supported_version) {
    //      return $version === $supported_version;
    //    };
    $filter = TRUE;
    if (count(array_filter(array_column($data, 'wotapi_action'), $filter)) === count($data)) {
      return TRUE;
    }
    throw WotapiActionException::fromError(Error::invalidRequest("Every request must include a 'wotapi_action' member with a value of versiono."));
    // Return TRUE;.
  }

  /**
   * Helper for creating an error RPC response exception.
   *
   * @param \Drupal\wotapi\Object\Error $error
   *   The JSON-RPC Error.
   * @param \Shaper\Util\Context $context
   *   The JSON-RPC request context.
   *
   * @return \Drupal\wotapi_action\Exception\WotapiActionException
   *   The new exception object.
   */
  protected function newException(Error $error, Context $context) {
    return WotapiActionException::fromError($error, $context[static::REQUEST_ID_KEY]);
  }

}
