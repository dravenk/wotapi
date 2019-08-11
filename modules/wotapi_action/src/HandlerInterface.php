<?php

namespace Drupal\wotapi_action;

use Drupal\Core\Session\AccountInterface;

/**
 * Interface for the handler.
 */
interface HandlerInterface {

  /**
   * The configuration array key for the JSON-RPC request object.
   *
   * @var string
   */
  const JSONRPC_REQUEST_KEY = 'jsonrpc_request';

  /**
   * Executes a batch of remote procedure calls.
   *
   * @param \Drupal\wotapi_action\Object\Request[] $requests
   *   The JSON-RPC requests.
   *
   * @return array
   *   The JSON-RPC responses, if any. Notifications are not returned.
   *
   * @throws \Drupal\wotapi_action\Exception\WotapiActionException
   */
  public function batch(array $requests);

  /**
   * Gets a action definition by action name.
   *
   * @param string $name
   *   The method name for which support should be determined.
   *
   * @return \Drupal\wotapi_action\WotapiActionInterface|null
   *   The method definition.
   */
  public function getAction($name);

  /**
   * The methods which are available to the given account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional). The account for which to get available methods. Defaults to
   *   the current user.
   *
   * @return \Drupal\wotapi_action\WotapiActionInterface[]
   *   The methods.
   */
  public function availableActions(AccountInterface $account = NULL);

  /**
   * The methods supported by the handler.
   *
   * @return \Drupal\wotapi_action\WotapiActionInterface[]
   *   The methods.
   */
  public function supportedActions();

  /**
   * Whether the given action is supported.
   *
   * @param string $name
   *   The method name for which support should be determined.
   *
   * @return bool
   *   Whether the handler supports the given method name.
   */
  public function supportsAction($name);

  /**
   * The supported JSON-RPC version.
   *
   * @return string
   *   The version.
   */
  public static function supportedVersion();

}
