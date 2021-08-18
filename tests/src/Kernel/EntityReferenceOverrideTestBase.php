<?php

namespace Drupal\Tests\entity_reference_override\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Base class for kernel tests.
 */
class EntityReferenceOverrideTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'field',
    'entity_test',
    'text',
    'entity_reference_override',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_mul');

    $field_name = 'field_reference_override';
    $entity_type = 'entity_test';
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'type' => 'entity_reference_override',
      'entity_type' => $entity_type,
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'entity_test_mul',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $entity_type,
      'label' => $field_name,
    ])->save();
  }

}
