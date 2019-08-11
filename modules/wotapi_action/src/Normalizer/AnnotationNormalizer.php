<?php

namespace Drupal\wotapi_action\Normalizer;

use Drupal\Component\Annotation\AnnotationInterface;
use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Url;
use Drupal\wotapi_action\Annotation\WotapiAction;
use Drupal\wotapi_action\Annotation\WotapiActionParameterDefinition;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * The normalizer class for annotated objects.
 */
class AnnotationNormalizer extends NormalizerBase {

  const DEPTH_KEY = __CLASS__ . '_depth';

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface
   */
  protected $serializer;

  /**
   * {@inheritdoc}
   */
  protected $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = [
    WotapiAction::class,
    WotapiActionParameterDefinition::class,
  ];

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $attributes = [];
    foreach ($object as $key => $value) {
      switch ($key) {
        case 'id':
        case 'call':
        case 'access':
          break;

        default:
          $child = $value instanceof AnnotationInterface ? $value->get() : $value;
          if (isset($context[static::DEPTH_KEY]) && $child instanceof AnnotationInterface || (is_array($child)) && Inspector::assertAllObjects($child, AnnotationInterface::class)) {
            if ($context[static::DEPTH_KEY] === 0) {
              break;
            }
            $context[static::DEPTH_KEY] -= 1;
          }
          $attributes[$key] = $this->serializer->normalize($child, $format, $context);
      }
    }
    $normalized = [
      'type' => static::getAnnotationType($object),
      'id' => $object->getId(),
      'attributes' => array_filter($attributes),
    ];
    if ($object instanceof WotapiAction) {
      $self = Url::fromRoute('wotapi_action.action_resource', [
        'action_id' => $object->id(),
      ])->setAbsolute()->toString(TRUE);
      $collection = Url::fromRoute('wotapi_action.action_collection')->setAbsolute()->toString(TRUE);
      $this->addCacheableDependency($context, $self);
      $this->addCacheableDependency($context, $collection);
      $normalized['links'] = [
        'self' => $self->getGeneratedUrl(),
        'collection' => $collection->getGeneratedUrl(),
      ];
    }
    if ($object instanceof WotapiActionParameterDefinition) {
      $normalized['schema'] = $object->getSchema();
    }
    return $normalized;
  }

  /**
   * Extract the annotation type.
   *
   * @param mixed $annotation
   *   The annotated object.
   *
   * @return string
   *   The type.
   */
  protected static function getAnnotationType($annotation) {
    switch (get_class($annotation)) {
      case WotapiAction::class:
        return 'WotapiAction';

      case WotapiActionParameterDefinition::class:
        return 'WotapiActionParameterDefinition';

      default:
        return get_class($annotation);
    }
  }

}
