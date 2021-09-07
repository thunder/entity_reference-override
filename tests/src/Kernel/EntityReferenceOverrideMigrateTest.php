<?php

namespace Drupal\Tests\entity_reference_override\Kernel;

/**
 * Run tests from EntityReferenceOverrideItemTest with field migration before.
 */
class EntityReferenceOverrideMigrateTest extends EntityReferenceOverrideItemTest {

  /**
   * {@inheritdoc}
   */
  protected $fieldType = 'entity_reference';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    /** @var \Drupal\entity_reference_override\EntityReferenceOverrideService $service */
    $service = \Drupal::service('entity_reference_override');
    $service->migrateEntityReferenceField('entity_test', 'field_media');
  }

}
