<?php

namespace Drupal\wotapi\WotApiResource;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;

/**
 * Represents a WOT:API document's "top level".
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @todo Add support for the missing optional 'wotapi' member or document why not.
 */
class WotApiDocumentTopLevel {

  /**
   * The data to normalize.
   *
   * @var \Drupal\wotapi\WotApiResource\ResourceIdentifierInterface|\Drupal\wotapi\WotApiResource\Data|\Drupal\wotapi\WotApiResource\ErrorCollection|\Drupal\Core\Field\EntityReferenceFieldItemListInterface
   */
  protected $data;

  /**
   * The metadata to normalize.
   *
   * @var array
   */
  protected $meta;

  /**
   * The links.
   *
   * @var \Drupal\wotapi\WotApiResource\LinkCollection
   */
  protected $links;


  /**
   * Resource objects that will be omitted from the response for access reasons.
   *
   * @var \Drupal\wotapi\WotApiResource\OmittedData
   */
  protected $omissions;

  /**
   * Instantiates a WotApiDocumentTopLevel object.
   *
   * @param \Drupal\wotapi\WotApiResource\ResourceIdentifierInterface|\Drupal\wotapi\WotApiResource\Data|\Drupal\wotapi\WotApiResource\ErrorCollection|\Drupal\Core\Field\EntityReferenceFieldItemListInterface $data
   *   The data to normalize. It can be either a ResourceObject, or a stand-in
   *   for one, or a collection of the same.
   * @param \Drupal\wotapi\WotApiResource\LinkCollection $links
   *   A collection of links to resources related to the top-level document.
   * @param array $meta
   *   (optional) The metadata to normalize.
   */
  public function __construct($data,LinkCollection $links, array $meta = []) {
    assert($data instanceof ResourceIdentifierInterface || $data instanceof Data || $data instanceof ErrorCollection || $data instanceof EntityReferenceFieldItemListInterface);
    $this->data = $data instanceof ResourceObjectData ? $data->getAccessible() : $data;
    $this->links = $links->withContext($this);
    $this->meta = $meta;
    $this->omissions = $data instanceof ResourceObjectData
      ? $data->getOmissions()
      : null;
  }

  /**
   * Gets the data.
   *
   * @return \Drupal\wotapi\WotApiResource\ResourceObject|\Drupal\wotapi\WotApiResource\Data|\Drupal\wotapi\WotApiResource\LabelOnlyResourceObject|\Drupal\wotapi\WotApiResource\ErrorCollection
   *   The data.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Gets the links.
   *
   * @return \Drupal\wotapi\WotApiResource\LinkCollection
   *   The top-level links.
   */
  public function getLinks() {
    return $this->links;
  }

  /**
   * Gets the metadata.
   *
   * @return array
   *   The metadata.
   */
  public function getMeta() {
    return $this->meta;
  }

  /**
   * Gets an OmittedData instance containing resources to be omitted.
   *
   * @return \Drupal\wotapi\WotApiResource\OmittedData
   *   The omissions.
   */
  public function getOmissions() {
    return $this->omissions;
  }

}
