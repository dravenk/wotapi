<?php

namespace Drupal\wotapi\Normalizer\Value;

/**
 * Helps normalize exceptions in compliance with the WOT:API spec.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 */
class HttpExceptionNormalizerValue extends CacheableNormalization {}
