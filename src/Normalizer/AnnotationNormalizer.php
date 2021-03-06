<?php

namespace Drupal\wotapi\Normalizer;

use Drupal\Component\Annotation\AnnotationInterface;
use Drupal\Component\Assertion\Inspector;
use Drupal\wotapi_action\Annotation\WotapiAction;
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
    // $normalized = [
    //      'type' => static::getAnnotationType($object),
    //      'id' => $object->getId(),
    //    ];
    $normalized = [];
    $at_type = $object->getAtType();
    if (!is_null($at_type)) {
      $normalized['@type'] = $at_type;
    }

    $normalized += $attributes;
    unset($normalized['at_type']);

    $action_input = call_user_func([$object->getClass(), 'input']);
    if (!is_null($action_input)) {
      $normalized['input'] = $action_input;
    }

    $action[$object->getId()] = $normalized;

    return $action;
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

      default:
        return get_class($annotation);
    }
  }

}
