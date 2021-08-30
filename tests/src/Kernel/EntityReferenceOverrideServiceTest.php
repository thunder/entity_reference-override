<?php

namespace Drupal\Tests\entity_reference_override\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Tests for the EntityReferenceOverrideService.
 */
class EntityReferenceOverrideServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'image',
    'text',
    'user',
    'field',
    'entity_test',
    'entity_reference_override',
  ];

  /**
   * Test the get overridden values method.
   *
   * @dataProvider getOverriddenValuesProvider
   *
   * @covers \Drupal\entity_reference_override\EntityReferenceOverrideService::getOverriddenValues
   */
  public function testGetOverriddenValues($field_type, $referenced_entity_value, $original_entity_value, $expected) {

    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'type' => $field_type,
      'entity_type' => 'entity_test',
      'cardinality' => -1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'field_test',
    ])->save();

    $referenced_entity = EntityTest::create([
      'name' => 'My name',
      'field_test' => $referenced_entity_value,
    ]);
    $original_entity = EntityTest::create([
      'name' => 'My name',
      'field_test' => $original_entity_value,
    ]);

    /** @var \Drupal\entity_reference_override\EntityReferenceOverrideService $service */
    $service = \Drupal::service('entity_reference_override');

    $this->assertEquals($expected, $service->getOverriddenValues($referenced_entity, $original_entity, ['field_test']));
  }

  /**
   * Data provider for testGetOverriddenValues().
   *
   * @return array[]
   *   The provider array.
   */
  public function getOverriddenValuesProvider() {
    return [
      ['string', [['value' => 'foo']], [['value' => 'foo']], []],
      [
        'string',
        [['value' => 'foo1']],
        [['value' => 'foo']],
        ['field_test' => [['value' => 'foo1']]],
      ],
      [
        'image',
        [['target_id' => 1, 'alt' => 'alt', 'button' => 'damn button']],
        [['target_id' => 1, 'alt' => 'alt']],
        [],
      ],
      [
        'image',
        [['target_id' => 1, 'alt' => 'alt override', 'button' => 'damn button']],
        [
          [
            'target_id' => 1,
            'alt' => 'alt',
            'title' => 'title',
            'height' => 2,
            'width' => 2,
          ],
        ],
        ['field_test' => [['alt' => 'alt override']]],
      ],
    ];
  }

}
