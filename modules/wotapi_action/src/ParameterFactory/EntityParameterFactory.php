<?php

namespace Drupal\wotapi_action\ParameterFactory;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\wotapi_action\Exception\WotapiActionException;
use Drupal\wotapi_action\Object\Error;
use Drupal\wotapi_action\ParameterDefinitionInterface;
use JsonSchema\Validator;
use Shaper\Util\Context;
use Shaper\Validator\InstanceofValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A factory to create loaded entities from entity type & UUID user input.
 */
class EntityParameterFactory extends ParameterFactoryBase {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * EntityParameterFactory constructor.
   *
   * @param \Drupal\wotapi_action\ParameterDefinitionInterface $definition
   *   The parameter definition.
   * @param \JsonSchema\Validator $validator
   *   The validator to ensure the user input is valid.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity type repository to load entities by UUID.
   */
  public function __construct(ParameterDefinitionInterface $definition, Validator $validator, EntityRepositoryInterface $entity_repository) {
    parent::__construct($definition, $validator);
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ParameterDefinitionInterface $definition, ContainerInterface $container) {
    return new static(
      $definition,
      $container->get('wotapi_action.schema_validator'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(ParameterDefinitionInterface $parameter_definition = NULL) {
    return [
      'type' => 'object',
      'properties' => [
        'type' => ['type' => 'string'],
        'uuid' => ['type' => 'string'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputValidator() {
    return new InstanceofValidator(EntityInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context = NULL) {
    try {
      if ($entity = $this->entityRepository->loadEntityByUuid($data['type'], $data['uuid'])) {
        return $entity;
      }
      throw WotapiActionException::fromError(Error::invalidParams('The requested entity could not be found.'));
    }
    catch (EntityStorageException $e) {
      throw WotapiActionException::fromError(Error::invalidParams('This entity type is not supported. Error: ' . $e->getMessage()));
    }
  }

}
