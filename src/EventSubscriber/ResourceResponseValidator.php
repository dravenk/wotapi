<?php

namespace Drupal\wotapi\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ModuleHandlerInterface;
use JsonSchema\Validator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Response subscriber that validates a WOT:API response.
 *
 * This must run after ResourceResponseSubscriber.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see \Drupal\rest\EventSubscriber\ResourceResponseSubscriber
 */
class ResourceResponseValidator implements EventSubscriberInterface {

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The WOT:API logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The schema validator.
   *
   * This property will only be set if the validator library is available.
   *
   * @var \JsonSchema\Validator|null
   */
  protected $validator;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The application's root file path.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * Constructs a ResourceResponseValidator object.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   * @param \Psr\Log\LoggerInterface $logger
   *   The WOT:API logger channel.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param string $app_root
   *   The application's root file path.
   */
  public function __construct(SerializerInterface $serializer, LoggerInterface $logger, ModuleHandlerInterface $module_handler, $app_root) {
    $this->serializer = $serializer;
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;
    $this->appRoot = $app_root;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse'];
    return $events;
  }

  /**
   * Sets the validator service if available.
   */
  public function setValidator(Validator $validator = NULL) {
    if ($validator) {
      $this->validator = $validator;
    }
    elseif (class_exists(Validator::class)) {
      $this->validator = new Validator();
    }
  }

  /**
   * Validates WOT:API responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event to process.
   */
  public function onResponse(FilterResponseEvent $event) {
    $response = $event->getResponse();
    if (strpos($response->headers->get('Content-Type'), 'application/vnd.api+json') === FALSE) {
      return;
    }

    $this->doValidateResponse($response, $event->getRequest());
  }

  /**
   * Wraps validation in an assert to prevent execution in production.
   *
   * @see self::validateResponse
   */
  public function doValidateResponse(Response $response, Request $request) {
    if (PHP_MAJOR_VERSION >= 7 || assert_options(ASSERT_ACTIVE)) {
      assert($this->validateResponse($response, $request), 'A WOT:API response failed validation (see the logs for details). Please report this in the issue queue on drupal.org');
    }
  }

  /**
   * Validates a response against the WOT:API specification.
   *
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response to validate.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request containing info about what to validate.
   *
   * @return bool
   *   FALSE if the response failed validation, otherwise TRUE.
   */
  protected function validateResponse(Response $response, Request $request) {
    // If the validator isn't set, then the validation library is not installed.
    if (!$this->validator) {
      return TRUE;
    }

    // Do not use Json::decode here since it coerces the response into an
    // associative array, which creates validation errors.
    $response_data = json_decode($response->getContent());
    if (empty($response_data)) {
      return TRUE;
    }

    $schema_ref = sprintf(
      'file://%s/schema.json',
      implode('/', [
        $this->appRoot,
        $this->moduleHandler->getModule('wotapi')->getPath(),
      ])
    );
    $generic_wotapi_schema = (object) ['$ref' => $schema_ref];

    return $this->validateSchema($generic_wotapi_schema, $response_data);
  }

  /**
   * Validates a string against a JSON Schema. It logs any possible errors.
   *
   * @param object $schema
   *   The JSON Schema object.
   * @param string $response_data
   *   The JSON string to validate.
   *
   * @return bool
   *   TRUE if the string is a valid instance of the schema. FALSE otherwise.
   */
  protected function validateSchema($schema, $response_data) {
    $this->validator->check($response_data, $schema);
    $is_valid = $this->validator->isValid();
    if (!$is_valid) {
      $this->logger->debug("Response failed validation.\nResponse:\n@data\n\nErrors:\n@errors", [
        '@data' => Json::encode($response_data),
        '@errors' => Json::encode($this->validator->getErrors()),
      ]);
    }
    return $is_valid;
  }

}
