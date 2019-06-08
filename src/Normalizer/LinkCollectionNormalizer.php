<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\Component\Utility\Crypt;
use Drupal\wotapi\WotApiResource\LinkCollection;
use Drupal\wotapi\WotApiResource\Link;
use Drupal\wotapi\Normalizer\Value\CacheableNormalization;

/**
 * Normalizes a LinkCollection object.
 *
 * The WOT:API specification has the concept of a "links collection". A links
 * collection is a JSON object where each member of the object is a
 * "link object". Unfortunately, this means that it is not possible to have more
 * than one link for a given key.
 *
 * When normalizing more than one link in a LinkCollection with the same key, a
 * unique and random string is appended to the link's key after a colon (:) to
 * differentiate the links.
 *
 * This may change with a later version of the WOT:API specification.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 */
class LinkCollectionNormalizer extends NormalizerBase {

  /**
   * The normalizer $context key name for the key of an individual link.
   *
   * @var string
   */
  const LINK_KEY = 'wotapi_links_object_link_key';

  /**
   * The normalizer $context key name for the context object of the link.
   *
   * @var string
   */
  const LINK_CONTEXT = 'wotapi_links_object_context';

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = LinkCollection::class;

  /**
   * A random string to use when hashing links.
   *
   * This string is unique per instance of a link collection, but always the
   * same within it. This means that link key hashes will be non-deterministic
   * for outside observers, but two links within the same collection will always
   * have the same hash value.
   *
   * This is not used for cryptographic purposes.
   *
   * @var string
   */
  protected $hashSalt;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof LinkCollection);
    $normalized = [];
    /* @var \Drupal\wotapi\WotApiResource\Link $link */
    foreach ($object as $key => $links) {
      $is_multiple = count($links) > 1;
      foreach ($links as $link) {
        $link_key = $is_multiple ? sprintf('%s:%s', $key, $this->hashByHref($link)) : $key;
        $attributes = $link->getTargetAttributes();
        $normalization = array_merge(['href' => $link->getHref()], !empty($attributes) ? ['meta' => $attributes] : []);
        $normalized[$link_key] = new CacheableNormalization($link, $normalization);
      }
    }
    return CacheableNormalization::aggregate($normalized);
  }

  /**
   * Hashes a link by its href.
   *
   * @param \Drupal\wotapi\WotApiResource\Link $link
   *   A link to be hashed.
   *
   * @return string
   *   A 7 character alphanumeric hash.
   */
  protected function hashByHref(Link $link) {
    if (!$this->hashSalt) {
      $this->hashSalt = Crypt::randomBytesBase64();
    }
    return substr(str_replace(['-', '_'], '', Crypt::hashBase64($this->hashSalt . $link->getHref())), 0, 7);
  }

}
