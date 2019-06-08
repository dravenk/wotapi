<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\wotapi\Exception\UnprocessableHttpEntityException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Normalizes and UnprocessableHttpEntityException.
 *
 * Normalizes an UnprocessableHttpEntityException in compliance with the JSON
 * API specification. A source pointer is added to help client applications
 * report validation errors, for example on an Entity edit form.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 *
 * @see http://wotapi.org/format/#error-objects
 */
class UnprocessableHttpEntityExceptionNormalizer extends HttpExceptionNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = UnprocessableHttpEntityException::class;

  /**
   * {@inheritdoc}
   */
  protected function buildErrorObjects(HttpException $exception) {
    /* @var $exception \Drupal\wotapi\Exception\UnprocessableHttpEntityException */
    $errors = parent::buildErrorObjects($exception);
    $error = $errors[0];
    unset($error['links']);

    $errors = [];
    $violations = $exception->getViolations();
    $entity_violations = $violations->getEntityViolations();
    foreach ($entity_violations as $violation) {
      /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
      $error['detail'] = 'Entity is not valid: '
        . $violation->getMessage();
      $error['source']['pointer'] = '/data';
      $errors[] = $error;
    }

    $entity = $violations->getEntity();
    foreach ($violations->getFieldNames() as $field_name) {
      $field_violations = $violations->getByField($field_name);
      $cardinality = $entity->get($field_name)
        ->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getCardinality();

      foreach ($field_violations as $violation) {
        /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
        $error['detail'] = $violation->getPropertyPath() . ': '
          . PlainTextOutput::renderFromHtml($violation->getMessage());

        $pointer = '/data/'
          . str_replace('.', '/', $violation->getPropertyPath());
        if ($cardinality == 1) {
          // Remove erroneous '/0/' index for single-value fields.
          $pointer = str_replace("/data/$field_name/0/", "/data/$field_name/", $pointer);
        }
        $error['source']['pointer'] = $pointer;

        $errors[] = $error;
      }
    }

    return $errors;
  }

}
