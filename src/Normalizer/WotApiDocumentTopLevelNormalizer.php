<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\wotapi\WotApiResource\ErrorCollection;
use Drupal\wotapi\WotApiResource\OmittedData;
use Drupal\wotapi\WotApiResource\ResourceObject;
use Drupal\wotapi\WotApiSpec;
use Drupal\wotapi\Normalizer\Value\CacheableOmission;
use Drupal\wotapi\WotApiResource\WotApiDocumentTopLevel;
use Drupal\wotapi\Normalizer\Value\CacheableNormalization;
use Drupal\wotapi\ResourceType\ResourceType;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Normalizes the top-level document according to the WOT:API specification.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/wotapi/issues/3032787
 * @see wotapi.api.php
 *
 * @see \Drupal\wotapi\WotApiResource\WotApiDocumentTopLevel
 */
class WotApiDocumentTopLevelNormalizer extends NormalizerBase implements DenormalizerInterface, NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = WotApiDocumentTopLevel::class;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The WOT:API resource type repository.
   *
   * @var \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * Constructs a WotApiDocumentTopLevelNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\wotapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The WOT:API resource type repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []){}

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof WotApiDocumentTopLevel);
    $data = $object->getData();

    $doc = [];
    foreach ($data as $key => $value) {
      // Add data.
      // @todo: remove this if-else and just call $this->serializer->normalize($data...) in https://www.drupal.org/project/wotapi/issues/3036285.
      if ($data instanceof EntityReferenceFieldItemListInterface) {
        $doc[$key] = $this->normalizeEntityReferenceFieldItemList($object, $format, $context);
      }
      else {
        $doc[$key] = $this->serializer->normalize($value, $format, $context);
      }
    }
    return CacheableNormalization::aggregate($doc)->withCacheableDependency((new CacheableMetadata())->addCacheContexts(['url.site']));
  }

  /**
   * Normalizes an error collection.
   *
   * @param \Drupal\wotapi\WotApiResource\WotApiDocumentTopLevel $document
   *   The document to normalize.
   * @param string $format
   *   The normalization format.
   * @param array $context
   *   The normalization context.
   *
   * @return \Drupal\wotapi\Normalizer\Value\CacheableNormalization
   *   The normalized document.
   *
   * @todo: refactor this to use CacheableNormalization::aggregate in https://www.drupal.org/project/wotapi/issues/3036284.
   */
  protected function normalizeErrorDocument(WotApiDocumentTopLevel $document, $format, array $context = []) {
    $normalized_values = array_map(function (HttpExceptionInterface $exception) use ($format, $context) {
      return $this->serializer->normalize($exception, $format, $context);
    }, (array) $document->getData()->getIterator());
    $cacheability = new CacheableMetadata();
    $errors = [];
    foreach ($normalized_values as $normalized_error) {
      $cacheability->addCacheableDependency($normalized_error);
      $errors = array_merge($errors, $normalized_error->getNormalization());
    }
    return new CacheableNormalization($cacheability, $errors);
  }

  /**
   * Normalizes an entity reference field, i.e. a relationship document.
   *
   * @param \Drupal\wotapi\WotApiResource\WotApiDocumentTopLevel $document
   *   The document to normalize.
   * @param string $format
   *   The normalization format.
   * @param array $context
   *   The normalization context.
   *
   * @return \Drupal\wotapi\Normalizer\Value\CacheableNormalization
   *   The normalized document.
   *
   * @todo: remove this in https://www.drupal.org/project/wotapi/issues/3036285.
   */
  protected function normalizeEntityReferenceFieldItemList(WotApiDocumentTopLevel $document, $format, array $context = []) {
    $data = $document->getData();
    $parent_entity = $data->getEntity();
    $resource_type = $this->resourceTypeRepository->get($parent_entity->getEntityTypeId(), $parent_entity->bundle());
    $context['resource_object'] = ResourceObject::createFromEntity($resource_type, $parent_entity);
    $normalized_relationship = $this->serializer->normalize($data, $format, $context);
    assert($normalized_relationship instanceof CacheableNormalization);
    unset($context['resource_object']);
    return new CacheableNormalization($normalized_relationship, $normalized_relationship->getNormalization()['data']);
  }

  /**
   * Hashes an omitted link.
   *
   * @param string $salt
   *   A hash salt.
   * @param string $link_href
   *   The omitted link.
   *
   * @return string
   *   A 7 character hash.
   */
  protected static function getLinkHash($salt, $link_href) {
    return substr(str_replace(['-', '_'], '', Crypt::hashBase64($salt . $link_href)), 0, 7);
  }

}
