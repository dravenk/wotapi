<?php

namespace Drupal\wotapi\ParamConverter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\wotapi\Routing\Routes;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter for upcasting entity UUIDs to full objects.
 *
 * @internal WOT:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see \Drupal\Core\ParamConverter\EntityConverter
 *
 */
class EntityUuidConverter extends EntityConverter {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Injects the language manager.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager to get the current content language.
   */
  public function setLanguageManager(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    // @see https://www.drupal.org/project/drupal/issues/2624770
    $entity_type_manager = isset($this->entityTypeManager)
      ? $this->entityTypeManager
      : $this->entityManager;
    $uuid_key = $entity_type_manager->getDefinition($entity_type_id)
      ->getKey('uuid');
    if ($storage = $entity_type_manager->getStorage($entity_type_id)) {
      if (!$entities = $storage->loadByProperties([$uuid_key => $value])) {
        return NULL;
      }
      $entity = reset($entities);
      // If the entity type is translatable, ensure we return the proper
      // translation object for the current context.
      if ($entity instanceof EntityInterface && $entity instanceof TranslatableInterface) {
        // @see https://www.drupal.org/project/drupal/issues/2624770
        $entity_repository = isset($this->entityRepository) ? $this->entityRepository : $this->entityManager;
        $entity = $entity_repository->getTranslationFromContext($entity, NULL, ['operation' => 'entity_upcast']);
        // WOT:API always has only one method per route.
        $method = $defaults[RouteObjectInterface::ROUTE_OBJECT]->getMethods()[0];
        if (in_array($method, ['PATCH', 'DELETE'], TRUE)) {
          $current_content_language = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
          if ($method === 'DELETE' && (!$entity->isDefaultTranslation() || $entity->language()->getId() !== $current_content_language)) {
            throw new MethodNotAllowedHttpException(['GET'], 'Deleting a resource object translation is not yet supported. See https://www.drupal.org/docs/8/modules/wotapi/translations.');
          }
          if ($method === 'PATCH' && $entity->language()->getId() !== $current_content_language) {
            $available_translations = implode(', ', array_keys($entity->getTranslationLanguages()));
            throw new MethodNotAllowedHttpException(['GET'], sprintf('The requested translation of the resource object does not exist, instead modify one of the translations that do exist: %s.', $available_translations));
          }
        }
      }
      return $entity;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return (
      (bool) Routes::getResourceTypeNameFromParameters($route->getDefaults()) &&
      !empty($definition['type']) && strpos($definition['type'], 'entity') === 0
    );
  }

}
