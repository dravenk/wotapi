<?php

namespace Drupal\wotapi_thing;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of Thing entities.
 *
 * @ingroup wotapi_thing
 */
class ThingListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Thing ID');
    $header['title'] = $this->t('Thing Title');
    $header['description'] = $this->t('Description');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\wotapi_thing\Entity\Thing */
    $row['id'] = $entity->id();
    $row['title'] = $entity->getTitle();
    $row['description'] = $entity->getDescription();

    return $row + parent::buildRow($entity);
  }

}
