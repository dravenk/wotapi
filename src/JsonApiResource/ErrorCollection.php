<?php

namespace Drupal\wotapi\WotApiResource;

use Drupal\Component\Assertion\Inspector;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * To be used when the primary data is `errors`.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 *
 * (The spec says the top-level `data` and `errors` members MUST NOT coexist.)
 * @see http://wotapi.org/format/#document-top-level
 *
 * @see http://wotapi.org/format/#error-objects
 */
class ErrorCollection implements \IteratorAggregate {

  /**
   * The HTTP exceptions.
   *
   * @var \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface[]
   */
  protected $errors;

  /**
   * Instantiates an ErrorCollection object.
   *
   * @param \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface[] $errors
   *   The errors.
   */
  public function __construct(array $errors) {
    assert(Inspector::assertAll(function ($error) {
      return $error instanceof HttpExceptionInterface;
    }, $errors));
    $this->errors = $errors;
  }

  /**
   * Returns an iterator for errors.
   *
   * @return \ArrayIterator
   *   An \ArrayIterator instance
   */
  public function getIterator() {
    return new \ArrayIterator($this->errors);
  }

}
