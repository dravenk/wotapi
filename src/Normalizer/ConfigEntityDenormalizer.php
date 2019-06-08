<?php
//
//namespace Drupal\wotapi\Normalizer;
//
//use Drupal\Core\Config\Entity\ConfigEntityInterface;
//use Drupal\wotapi\ResourceType\ResourceType;
//
///**
// * Converts the Drupal config entity object to a WOT:API array structure.
// *
// * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
// *   class may change at any time and this will break any dependencies on it.
// *
// * @see https://www.drupal.org/project/wotapi/issues/3032787
// * @see wotapi.api.php
// */
//final class ConfigEntityDenormalizer extends EntityDenormalizerBase {
//
//  /**
//   * {@inheritdoc}
//   */
//  protected $supportedInterfaceOrClass = ConfigEntityInterface::class;
//
//  /**
//   * {@inheritdoc}
//   */
//  protected function prepareInput(array $data, ResourceType $resource_type, $format, array $context) {
//    $prepared = [];
//    foreach ($data as $key => $value) {
//      $prepared[$resource_type->getInternalName($key)] = $value;
//    }
//    return $prepared;
//  }
//
//}
