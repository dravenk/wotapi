<?php

namespace Drupal\wotapi\WotApiResource;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\TypedDataInternalPropertiesHelper;
use Drupal\Core\Url;
use Drupal\wotapi\ResourceType\ResourceType;
use Drupal\wotapi\Routing\Routes;
use Drupal\wotapi_thing\Entity\Thing;

/**
 * Represents a WOT:API resource object.
 *
 * This value object wraps a Drupal entity so that it can carry a WOT:API
 * resource type object alongside it. It also helps abstract away differences
 * between config and content entities within the WOT:API codebase.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 */
class ResourceObject implements CacheableDependencyInterface, ResourceIdentifierInterface {

  use CacheableDependencyTrait;
  use ResourceIdentifierTrait;

  /**
   * The object's fields.
   *
   * This refers to "fields" in the WOT:API sense of the word. Config entities
   * do not have real fields, so in that case, this will be an array of values
   * for config entity attributes.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface[]|mixed[]
   */
  protected $fields;

  /**
   * The resource object's links.
   *
   * @var \Drupal\wotapi\WotApiResource\LinkCollection
   */
  protected $links;

  /**
   * The resource object's source field.
   *
   * field_switch(source_field)
   *   - target:$this(OnOffProperty)
   *
   * @var \Drupal\Core\Field\FieldItemListInterface
   */
  protected $source_field;

  /**
   * ResourceObject constructor.
   *
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $cacheability
   *   The cacheability for the resource object.
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The WOT:API resource type of the resource object.
   * @param string $id
   *   The resource object's ID.
   * @param array $fields
   *   An array of the resource object's fields, keyed by public field name.
   * @param \Drupal\wotapi\WotApiResource\LinkCollection $links
   *   The links for the resource object.
   */
  public function __construct(CacheableDependencyInterface $cacheability, ResourceType $resource_type, $id, array $fields, LinkCollection $links) {
    $this->setCacheability($cacheability);
    $this->resourceType = $resource_type;
    $this->resourceIdentifier = new ResourceIdentifier($resource_type, $id);
    $this->fields = $fields;
    $this->links = $links->withContext($this);
  }

  /**
   * Creates a new ResourceObject from an entity.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The WOT:API resource type of the resource object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be represented by this resource object.
   * @param \Drupal\wotapi\WotApiResource\LinkCollection $links
   *   (optional) Any links for the resource object, if a `self` link is not
   *   provided, one will be automatically added if the resource is locatable
   *   and is not an internal entity.
   *
   * @return static
   *   An instantiated resource object.
   */
  public static function createFromEntity(ResourceType $resource_type, EntityInterface $entity, LinkCollection $links = NULL) {
    return new static(
      $entity,
      $resource_type,
      $entity->uuid(),
      static::extractFieldsFromEntity($resource_type, $entity),
      static::buildLinksFromEntity($resource_type, $entity, $links ?: new LinkCollection([]))
    );
  }

  /**
   * Whether the resource object has the given field.
   *
   * @param string $public_field_name
   *   A public field name.
   *
   * @return bool
   *   TRUE if the resource object has the given field, FALSE otherwise.
   */
  public function hasField($public_field_name) {
    return isset($this->fields[$public_field_name]);
  }

  /**
   * Gets the given field.
   *
   * @param string $public_field_name
   *   A public field name.
   *
   * @return mixed|\Drupal\Core\Field\FieldItemListInterface|null
   *   The field or NULL if the resource object does not have the given field.
   *
   * @see ::extractFields()
   */
  public function getField($public_field_name) {
    return $this->hasField($public_field_name) ? $this->fields[$public_field_name] : NULL;
  }

  /**
   * Gets the ResourceObject's fields.
   *
   * @return array
   *   The resource object's fields, keyed by public field name.
   *
   * @see ::extractFields()
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * Gets the ResourceObject's links.
   *
   * @return \Drupal\wotapi\WotApiResource\LinkCollection
   *   The resource object's links.
   */
  public function getLinks() {
    return $this->links;
  }

  /**
   * Gets the ResourceObject's thing.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface $source_field
   *   The resource object's $source_field.
   */
  public function getSourceField() {
    return $this->source_field;
  }


  /**
   * Sets thing; useful for value object constructors.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $source_field
   *   The resource object's $source_field.
   *
   * @return $this
   */
  public function setSourceField(FieldItemListInterface $source_field) {
    $this->source_field = $source_field;
    return $this;
  }

  /**
   * Gets a Url for the ResourceObject.
   *
   * @return \Drupal\Core\Url
   *   The URL for the identified resource object.
   *
   * @throws \LogicException
   *   Thrown if the resource object is not locatable.
   *
   * @see \Drupal\wotapi\ResourceType\ResourceTypeRepository::isLocatableResourceType()
   */
  public function toUrl() {
    foreach ($this->links as $key => $link) {
      if ($key === 'self') {
        $first = reset($link);
        return $first->getUri();
      }
    }
    throw new \LogicException('A Url does not exist for this resource object because its resource type is not locatable.');
  }

  /**
   * Extracts the entity's fields.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The WOT:API resource type of the given entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity from which fields should be extracted.
   *
   * @return mixed|\Drupal\Core\Field\FieldItemListInterface[]
   *   If the resource object represents a content entity, the fields will be
   *   objects satisfying FieldItemListInterface. If it represents a config
   *   entity, the fields will be scalar values or arrays.
   */
  protected static function extractFieldsFromEntity(ResourceType $resource_type, EntityInterface $entity) {
    assert($entity instanceof ContentEntityInterface || $entity instanceof ConfigEntityInterface);
    return $entity instanceof ContentEntityInterface
      ? static::extractContentEntityFields($resource_type, $entity)
      : static::extractConfigEntityFields($resource_type, $entity);
  }

  /**
   * Builds a LinkCollection for the given entity.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The WOT:API resource type of the given entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to build links.
   * @param \Drupal\wotapi\WotApiResource\LinkCollection $links
   *   (optional) Any extra links for the resource object, if a `self` link is
   *   not provided, one will be automatically added if the resource is
   *   locatable and is not an internal entity.
   *
   * @return \Drupal\wotapi\WotApiResource\LinkCollection
   *   The built links.
   */
  protected static function buildLinksFromEntity(ResourceType $resource_type, EntityInterface $entity, LinkCollection $links) {
    if ($resource_type->isLocatable() && !$resource_type->isInternal()) {
      $self_url = Url::fromRoute(Routes::getRouteName($resource_type, 'individual'), ['entity' => $entity->uuid()]);
      $links = $links->withLink('self', new Link(new CacheableMetadata(), $self_url, ['self']));

    }
    return $links;
  }

  /**
   * Extracts a content entity's fields.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The WOT:API resource type of the given entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The config entity from which fields should be extracted.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface[]
   *   The fields extracted from a content entity.
   */
  protected static function extractContentEntityFields(ResourceType $resource_type, ContentEntityInterface $entity) {
    $output = [];
    $fields = TypedDataInternalPropertiesHelper::getNonInternalProperties($entity->getTypedData());
    // Filter the array based on the field names.
    $enabled_field_names = array_filter(
      array_keys($fields),
      [$resource_type, 'isFieldEnabled']
    );

    // The "label" field needs special treatment: some entity types have a label
    // field that is actually backed by a label callback.
    $entity_type = $entity->getEntityType();
    if ($entity_type->hasLabelCallback()) {
      $fields[static::getLabelFieldName($entity)]->value = $entity->label();
    }

    // Return a sub-array of $output containing the keys in $enabled_fields.
    $input = array_intersect_key($fields, array_flip($enabled_field_names));
    foreach ($input as $field_name => $field_value) {
      $public_field_name = $resource_type->getPublicName($field_name);
      $output[$public_field_name] = $field_value;
    }
    return $output;
  }

  /**
   * Determines the entity type's (internal) label field name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity from which fields should be extracted.
   *
   * @return string
   *   The label field name.
   */
  protected static function getLabelFieldName(EntityInterface $entity) {
    $label_field_name = $entity->getEntityType()->getKey('label');
    // @todo Remove this work-around after https://www.drupal.org/project/drupal/issues/2450793 lands.
    if ($entity->getEntityTypeId() === 'user') {
      $label_field_name = 'name';
    }
    return $label_field_name;
  }

  /**
   * Extracts a config entity's fields.
   *
   * @param \Drupal\wotapi\ResourceType\ResourceType $resource_type
   *   The WOT:API resource type of the given entity.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The config entity from which fields should be extracted.
   *
   * @return array
   *   The fields extracted from a config entity.
   */
  protected static function extractConfigEntityFields(ResourceType $resource_type, ConfigEntityInterface $entity) {
    $enabled_public_fields = [];
    $fields = $entity->toArray();
    // Filter the array based on the field names.
    $enabled_field_names = array_filter(array_keys($fields), function ($internal_field_name) use ($resource_type) {
      // Config entities have "fields" which aren't known to the resource type,
      // these fields should not be excluded because they cannot be enabled or
      // disabled.
      return !$resource_type->hasField($internal_field_name) || $resource_type->isFieldEnabled($internal_field_name);
    });
    // Return a sub-array of $output containing the keys in $enabled_fields.
    $input = array_intersect_key($fields, array_flip($enabled_field_names));
    /* @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    foreach ($input as $field_name => $field_value) {
      $public_field_name = $resource_type->getPublicName($field_name);
      $enabled_public_fields[$public_field_name] = $field_value;
    }
    return $enabled_public_fields;
  }

}
