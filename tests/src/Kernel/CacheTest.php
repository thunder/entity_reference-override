<?php

namespace Drupal\Tests\entity_reference_override\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Component\Serialization\Json;

/**
 * Testing caching related use cases.
 */
class CacheTest extends EntityReferenceOverrideTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $field_name = 'field_reference_override_2';
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

    $field_name = 'field_description';
    $entity_type = 'entity_test_mul';
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'type' => 'text_long',
      'entity_type' => $entity_type,
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $entity_type,
      'label' => $field_name,
    ])->save();
  }

  /**
   * Testing that all expected cache keys exists.
   */
  public function testCacheKeys() {
    $referenced_entity = EntityTestMul::create([
      'name' => 'Referenced entity',
      'field_description' => 'Description',
    ]);
    $referenced_entity->save();

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('entity_test');

    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_reference_override' => [
        'target_id' => $referenced_entity->id(),
      ],
      'field_reference_override_2' => [
        [
          'target_id' => $referenced_entity->id(),
        ],
        [
          'target_id' => $referenced_entity->id(),
        ],
      ],
    ]);
    $entity->save();

    $render = $view_builder->view($entity->field_reference_override->entity);
    $this->assertNotContains('entity_reference_override:', $render['#cache']['keys']);

    $render = $view_builder->view($entity->field_reference_override_2->entity);
    $this->assertNotContains('entity_reference_override:', $render['#cache']['keys']);

    $entity->field_reference_override->overwritten_property_map = Json::encode([
      'field_description' => 'Overridden description',
    ]);
    $entity->field_reference_override_2->get(1)->overwritten_property_map = Json::encode([
      'field_description' => 'Overridden second description',
    ]);
    $entity->save();

    $render = $view_builder->view($entity->field_reference_override->entity);
    $this->assertContains('entity_reference_override:entity_test:entity_test.field_reference_override.0', $render['#cache']['keys']);

    $render = $view_builder->view($entity->field_reference_override_2->entity);
    $this->assertNotContains('entity_reference_override:', $render['#cache']['keys']);
    $render = $view_builder->view($entity->field_reference_override_2->get(1)->entity);
    $this->assertContains('entity_reference_override:entity_test:entity_test.field_reference_override_2.1', $render['#cache']['keys']);
  }

  /**
   * Testing that referencing the same entity in multiple fields works.
   */
  public function testReferencingSameEntityInMultipleFields() {
    $referenced_entity = EntityTestMul::create([
      'name' => 'Referenced entity',
      'field_description' => 'Description',
    ]);
    $referenced_entity->save();

    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_reference_override' => [
        'target_id' => $referenced_entity->id(),
        'overwritten_property_map' => Json::encode([
          'field_description' => 'Overridden description',
        ]),
      ],
      'field_reference_override_2' => [
        'target_id' => $referenced_entity->id(),
      ],
    ]);

    $entity->save();

    $this->assertEquals("Overridden description", $entity->field_reference_override->entity->field_description->value);
    $this->assertEquals("Description", $entity->field_reference_override_2->entity->field_description->value);
  }

}
