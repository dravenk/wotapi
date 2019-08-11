<?php

namespace Drupal\wotapi_action\ParameterFactory;

use Drupal\wotapi_action\ParameterDefinitionInterface;
use Shaper\Util\Context;

/**
 * A parameter factory to handle paginated responses.
 */
class PaginationParameterFactory extends ParameterFactoryBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(ParameterDefinitionInterface $parameter_definition) {
    return [
      'type' => 'object',
      'properties' => [
        'limit' => [
          'type' => 'integer',
          'minimum' => 0,
        ],
        'offset' => [
          'type' => 'integer',
          'minimum' => 0,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputValidator() {
    // The input is the same as the output.
    return $this->getInputValidator();
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context = NULL) {
    return $data;
  }

}
