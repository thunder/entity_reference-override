<?php

namespace Drupal\entity_reference_override\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;

/**
 * Defines the class for "per reference field" caching.
 *
 * Cache context ID: 'overridden_reference_field'.
 */
class OverriddenReferenceFieldCacheContext implements CacheContextInterface {

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Overridden Reference Field');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
