<?php

namespace Drupal\wotapi\EventSubscriber;

use Drupal\wotapi\WotApiResource\ErrorCollection;
use Drupal\wotapi\WotApiResource\WotApiDocumentTopLevel;
use Drupal\wotapi\WotApiResource\LinkCollection;
use Drupal\wotapi\WotApiResource\NullIncludedData;
use Drupal\wotapi\ResourceResponse;
use Drupal\wotapi\Routing\Routes;
use Drupal\serialization\EventSubscriber\DefaultExceptionSubscriber as SerializationDefaultExceptionSubscriber;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Serializes exceptions in compliance with the  WOT:API specification.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 */
class DefaultExceptionSubscriber extends SerializationDefaultExceptionSubscriber {

  /**
   * {@inheritdoc}
   */
  protected static function getPriority() {
    return parent::getPriority() + 25;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['api_json'];
  }

  /**
   * {@inheritdoc}
   */
  public function onException(GetResponseForExceptionEvent $event) {
    if (!$this->isWotApiExceptionEvent($event)) {
      return;
    }
    if (($exception = $event->getException()) && !$exception instanceof HttpException) {
      $exception = new HttpException(500, $exception->getMessage(), $exception);
      $event->setException($exception);
    }

    $this->setEventResponse($event, $exception->getStatusCode());
  }

  /**
   * {@inheritdoc}
   */
  protected function setEventResponse(GetResponseForExceptionEvent $event, $status) {
    /* @var \Symfony\Component\HttpKernel\Exception\HttpException $exception */
    $exception = $event->getException();
    $response = new ResourceResponse(new WotApiDocumentTopLevel(new ErrorCollection([$exception]), new LinkCollection([])), $exception->getStatusCode(), $exception->getHeaders());
    $response->addCacheableDependency($exception);
    $event->setResponse($response);
  }

  /**
   * Check if the error should be formatted using WOT:API.
   *
   * The WOT:API format is supported if the format is explicitly set or the
   * request is for a known WOT:API route.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $exception_event
   *   The exception event.
   *
   * @return bool
   *   TRUE if it needs to be formatted using WOT:API. FALSE otherwise.
   */
  protected function isWotApiExceptionEvent(GetResponseForExceptionEvent $exception_event) {
    $request = $exception_event->getRequest();
    $parameters = $request->attributes->all();
    return $request->getRequestFormat() === 'api_json' || (bool) Routes::getResourceTypeNameFromParameters($parameters);
  }

}
