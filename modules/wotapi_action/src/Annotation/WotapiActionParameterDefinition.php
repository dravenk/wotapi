<?php

namespace Drupal\wotapi_action\Annotation;

use Drupal\wotapi_action\ParameterDefinitionInterface;

/**
 * Defines a JsonRpcParameterDefinition annotation object.
 *
 * @see \Drupal\wotapi_action\Plugin\wotapi_actionServiceManager
 * @see plugin_api
 *
 * @Annotation
 */
class WotapiActionParameterDefinition implements ParameterDefinitionInterface {

  /**
   * The name of the parameter if the params are by-name, an offset otherwise.
   *
   * @var string|int
   */
  protected $id;

  /**
   * The parameter schema.
   *
   * @var array
   */
  public $schema = NULL;

  /**
   * A description of the parameter.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The parameter factory.
   *
   * @var string
   */
  public $factory;

  /**
   * Whether the parameter is required.
   *
   * @var bool
   */
  public $required;

  /**
   * {@inheritdoc}
   */
  public function getFactory() {
    return $this->factory;
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
  public function isRequired() {
    return isset($this->required) ? $this->required : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function get() {
    return $this;
  }

  /**
   * Sets the parameter ID.
   *
   * @param string|int $id
   *   The ID to set.
   */
  public function setId($id) {
    $this->id = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->id;
  }

}
