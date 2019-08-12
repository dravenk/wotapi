<?php

namespace Drupal\wotapi_action\Annotation;

use Drupal\Component\Annotation\AnnotationBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\wotapi_action\WotapiActionInterface;

/**
 * Defines a WotapiActionParameterDefinition annotation object.
 *
 * @see \Drupal\wotapi_action\Plugin\WotapiActionManager
 * @see plugin_api
 *
 * @Annotation
 */
class WotapiAction extends AnnotationBase implements WotapiActionInterface {

  /**
   * The access required to use this RPC method.
   *
   * @var mixed
   */
  public $access;

  /**
   * The class method to call.
   *
   * Optional. If the method ID is 'foo.bar', this defaults to 'bar'. If the
   * method ID does not contain a dot (.), defaults to 'execute'.
   *
   * @var string
   */
  public $call;

  /**
   * How to use this method.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $usage;

  /**
   * A semantic @type (a string identifying a type from the linked @context).
   *
   * @var string
   */
  public $at_type;

  /**
   * A title (A string providing a human friendly name).
   *
   * @var string
   */
  public $title;

  /**
   * A description (A string providing a human friendly description).
   *
   * @var string
   */
  public $description;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function call() {
    if (!isset($this->call)) {
      $this->call = 'execute';
    }
    return $this->call;
  }

  /**
   * {@inheritdoc}
   */
  public function getUsage() {
    $this->usage;
  }

  /**
   * {@inheritdoc}
   */
  public function getAtType() {
   return $this->at_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function get() {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'execute', AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?: \Drupal::currentUser();
    switch ($operation) {
      case 'execute':
        if (is_callable($this->access)) {
          return call_user_func_array($this->access, [
            $operation,
            $account,
            $return_as_object,
          ]);
        }
        $access_result = AccessResult::allowed();
        foreach ($this->access as $permission) {
          $access_result = $access_result->andIf(AccessResult::allowedIfHasPermission($account, $permission));
        }
        break;

      case 'view':
        $access_result = $this->access('execute', $account, $return_as_object);
        break;

      default:
        $access_result = AccessResult::neutral();
        break;
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

}
