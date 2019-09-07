<?php

namespace Drupal\wotapi;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Property type entities.
 */
class PropertyTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Property type');
    $header['id'] = $this->t('Machine name');
    // $header['title'] = $this->t('Title');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    // $row['title'] = $entity->ti;
    // You probably want a few more properties here...
    return $row + parent::buildRow($entity);
  }

}
