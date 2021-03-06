<?php

namespace Drupal\wotapi;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of Property entities.
 *
 * @ingroup wotapi
 */
class PropertyListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Property ID');
    // $header['title'] = $this->t('Property Name');
    //    $header['description'] = $this->t('Description');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\wotapi\Entity\Property */
    $row['id'] = $entity->id();
    // $row['title'] = $entity->getTitle();
    //    $row['description'] = $entity->getDescription();
    return $row + parent::buildRow($entity);
  }

}
