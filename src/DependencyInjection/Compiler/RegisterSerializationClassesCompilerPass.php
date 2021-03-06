<?php

namespace Drupal\wotapi\DependencyInjection\Compiler;

use Drupal\serialization\RegisterSerializationClassesCompilerPass as DrupalRegisterSerializationClassesCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds services tagged WOT:API-only normalizers to the Serializer.
 *
 * Services tagged with 'wotapi_normalizer' will be added to the WOT:API
 * serializer. No extensions can provide such services.
 *
 * WOT:API does respect generic (non-WOT:API) DataType-level normalizers.
 *
 * @internal WOT:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 */
class RegisterSerializationClassesCompilerPass extends DrupalRegisterSerializationClassesCompilerPass {

  /**
   * The service ID.
   *
   * @const string
   */
  const OVERRIDDEN_SERVICE_ID = 'wotapi.serializer';

  /**
   * The service tag that only WOT:API normalizers should use.
   *
   * @const string
   */
  const OVERRIDDEN_SERVICE_NORMALIZER_TAG = 'wotapi_normalizer';

  /**
   * The service tag that only WOT:API encoders should use.
   *
   * @const string
   */
  const OVERRIDDEN_SERVICE_ENCODER_TAG = 'wotapi_encoder';

  /**
   * The ID for the WOT:API format.
   *
   * @const string
   */
  const FORMAT = 'api_json';

  /**
   * Adds services to the WOT:API Serializer.
   *
   * This code is copied from the class parent with two modifications. The
   * service id has been changed and the service tag has been updated.
   *
   * ID: 'serializer' -> 'wotapi.serializer'
   * Tag: 'normalizer' -> 'wotapi_normalizer'
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container to process.
   */
  public function process(ContainerBuilder $container) {
    $definition = $container->getDefinition(static::OVERRIDDEN_SERVICE_ID);

    // Retrieve registered Normalizers and Encoders from the container.
    foreach ($container->findTaggedServiceIds(static::OVERRIDDEN_SERVICE_NORMALIZER_TAG) as $id => $attributes) {
      // Normalizers are not an API: mark private.
      $container->getDefinition($id)->setPublic(FALSE);

      $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
      $normalizers[$priority][] = new Reference($id);
    }
    foreach ($container->findTaggedServiceIds(static::OVERRIDDEN_SERVICE_ENCODER_TAG) as $id => $attributes) {
      // Encoders are not an API: mark private.
      $container->getDefinition($id)->setPublic(FALSE);

      $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
      $encoders[$priority][] = new Reference($id);
    }

    // Add the registered Normalizers and Encoders to the Serializer.
    if (!empty($normalizers)) {
      $definition->replaceArgument(0, $this->sort($normalizers));
    }
    if (!empty($encoders)) {
      $definition->replaceArgument(1, $this->sort($encoders));
    }

  }

}
