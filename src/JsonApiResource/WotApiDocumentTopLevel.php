<?php

namespace Drupal\wotapi\WotApiResource;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;

/**
 * Represents a WOT:API document's "top level".
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 *
 * @see http://wotapi.org/format/#document-top-level
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
   * The includes to normalize.
   *
   * @var \Drupal\wotapi\WotApiResource\IncludedData
   */
  protected $includes;

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
   * @param \Drupal\wotapi\WotApiResource\IncludedData $includes
   *   A WOT:API Data object containing resources to be included in the
   *   response document or NULL if there should not be includes.
   * @param \Drupal\wotapi\WotApiResource\LinkCollection $links
   *   A collection of links to resources related to the top-level document.
   * @param array $meta
   *   (optional) The metadata to normalize.
   */
  public function __construct($data, IncludedData $includes, LinkCollection $links, array $meta = []) {
    assert($data instanceof ResourceIdentifierInterface || $data instanceof Data || $data instanceof ErrorCollection || $data instanceof EntityReferenceFieldItemListInterface);
    assert(!$data instanceof ErrorCollection || $includes instanceof NullIncludedData);
    $this->data = $data instanceof ResourceObjectData ? $data->getAccessible() : $data;
    $this->includes = $includes->getAccessible();
    $this->links = $links->withContext($this);
    $this->meta = $meta;
    $this->omissions = $data instanceof ResourceObjectData
      ? OmittedData::merge($data->getOmissions(), $includes->getOmissions())
      : $includes->getOmissions();
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
   * Gets a WOT:API Data object of resources to be included in the response.
   *
   * @return \Drupal\wotapi\WotApiResource\IncludedData
   *   The includes.
   */
  public function getIncludes() {
    return $this->includes;
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