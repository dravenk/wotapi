<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\wotapi\WotApiResource\ResourceIdentifier;
use Drupal\wotapi\Normalizer\Value\CacheableNormalization;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Normalizes a Relationship according to the WOT:API specification.
 *
 * Normalizer class for relationship elements. A relationship can be anything
 * that points to an entity in a WOT:API resource.
 */
class ResourceIdentifierNormalizer extends NormalizerBase implements DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = ResourceIdentifier::class;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * RelationshipNormalizer constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity field manager.
   */
  public function __construct(EntityFieldManagerInterface $field_manager) {
    $this->fieldManager = $field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof ResourceIdentifier);

    $normalization = [
      'type' => $object->getTypeName(),
      'id' => $object->getId(),
    ];

    return CacheableNormalization::permanent($normalization);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = null, array $context = []){}

}
