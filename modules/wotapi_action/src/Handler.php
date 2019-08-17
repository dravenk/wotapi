<?php

namespace Drupal\wotapi_action;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\wotapi_action\Exception\WotapiActionException;
use Drupal\wotapi_action\Object\Error;
use Drupal\wotapi_action\Object\ParameterBag;
use Drupal\wotapi_action\Object\Request;
use Drupal\wotapi_action\Object\Response;

/**
 * Manages all the JSON-RPC business logic.
 */
class Handler implements HandlerInterface {

  /**
   * The JSON-RPC method plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $methodManager;

  /**
   * Handler constructor.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $method_manager
   *   The plugin manager for the JSON RPC methods.
   */
  public function __construct(PluginManagerInterface $method_manager) {
    $this->methodManager = $method_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function batch(array $requests) {
    return array_filter(array_map(function (Request $request) {
      return $this->doRequest($request);
    }, $requests));
  }

  /**
   * {@inheritdoc}
   */
  public function supportedActions() {
    return $this->methodManager->getDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsAction($name) {
    return !is_null($this->getAction($name));
  }

  /**
   * {@inheritdoc}
   */
  public function availableActions(AccountInterface $account = NULL) {
    return array_filter($this->supportedActions(), function (WotapiActionInterface $method) {
      return $method->access('execute');
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getAction($name) {
    return $this->methodManager->getDefinition($name, FALSE);
  }

  /**
   * Executes an RPC call and returns a JSON-RPC response.
   *
   * @param \Drupal\wotapi_action\Object\Request $request
   *   The JSON-RPC request.
   *
   * @return \Drupal\wotapi_action\Object\Response|null
   *   The JSON-RPC response.
   */
  protected function doRequest(Request $request) {
    // Helper closure to handle eventual exceptions.
    $handle_exception = function ($e, Request $request) {
      if (!$e instanceof WotapiActionException) {
        $id = $request->isNotification() ? FALSE : $request->id();
        $e = WotapiActionException::fromPrevious($e, $id);
      }
      return $e->getResponse();
    };
    try {
      $result = $this->doExecution($request);
      if ($request->isNotification()) {
        return NULL;
      }
      $rpc_response = $result instanceof Response
        ? $result
        : new Response($request->id(), $result);
      $methodPluginClass = $this->getAction($request->getAction())->getClass();
      $result_schema = call_user_func([$methodPluginClass, 'outputSchema']);
      $rpc_response->setResultSchema($result_schema);
      return $rpc_response;
    }
    // Catching Throwable allows us to recover from more kinds of exceptions
    // that might occur in badly written 3rd party code.
    catch (\Throwable $e) {
      return $handle_exception($e, $request);
    }
    // @TODO: Remove the following when PHP7 is the minimum supported version.
    catch (\Exception $e) {
      return $handle_exception($e, $request);
    }
  }

  /**
   * Gets an anonymous function which executes the RPC method.
   *
   * @param \Drupal\wotapi_action\Object\Request $request
   *   The JSON-RPC request.
   *
   * @return \Drupal\wotapi_action\Object\Response|null
   *   The JSON-RPC response.
   *
   * @throws \Drupal\wotapi_action\Exception\WotapiActionException
   */
  protected function doExecution(Request $request) {
    if ($action = $this->getAction($request->getAction())) {
      $this->checkAccess($action);
      $configuration = [HandlerInterface::JSONRPC_REQUEST_KEY => $request];
      $executable = $this->getExecutable($action, $configuration);
      return $request->hasParams()
        ? $executable->execute(NULL)
        : $executable->execute(new ParameterBag([]));
    }
    else {
      throw WotapiActionException::fromError(Error::methodNotFound($action->id()));
    }
  }

  /**
   * Gets an executable instance of an RPC method.
   *
   * @param \Drupal\wotapi_action\WotapiActionInterface $method
   *   The method definition.
   * @param array $configuration
   *   Method configuration.
   *
   * @return object
   *   The executable method.
   *
   * @throws \Drupal\wotapi_action\Exception\WotapiActionException
   *   In case of error.
   */
  protected function getExecutable(WotapiActionInterface $method, array $configuration) {
    try {
      return $this->methodManager->createInstance($method->id(), $configuration);
    }
    catch (PluginException $e) {
      throw WotapiActionException::fromError(Error::methodNotFound($method->id()));
    }
  }

  /**
   * Check execution access.
   *
   * @param \Drupal\wotapi_action\WotapiActionInterface $method
   *   The method for which to check access.
   *
   * @throws \Drupal\wotapi_action\Exception\WotapiActionException
   */
  protected function checkAccess(WotapiActionInterface $method) {
    // TODO: Add cacheability metadata here.
    /* @var \Drupal\wotapi_action\WotapiActionInterface $method_definition */
    $access_result = $method->access('execute', NULL, TRUE);
    if (!$access_result->isAllowed()) {
      $reason = 'Access Denied';
      if ($access_result instanceof AccessResultReasonInterface && ($detail = $access_result->getReason())) {
        $reason .= ': ' . $detail;
      }
      throw WotapiActionException::fromError(Error::invalidRequest($reason, $access_result));
    }
  }

}
