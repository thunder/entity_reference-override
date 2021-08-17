<?php

namespace Drupal\Tests\entity_reference_override\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Testing translation related use cases.
 */
class TranslationTest extends EntityReferenceOverrideTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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

    ConfigurableLanguage::create([
      'id' => 'de',
      'label' => 'German',
    ])->save();
  }

  /**
   * Test with translatable parent entity and an untranslatable reference.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTranslatableParentWithUntranslatableReference() {
    $referenced_entity = EntityTestMul::create([
      'name' => 'Referenced entity',
      'field_description' => 'Main description',
    ]);
    $referenced_entity->save();

    // Create english parent entity.
    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_reference_override' => [
        'target_id' => $referenced_entity->id(),
      ],
      'langcode' => 'en',
    ]);
    $entity->save();

    // Add german translation.
    $entity->addTranslation('de', $entity->toArray());
    $entity->save();

    $entity->field_reference_override->overwritten_property_map = [
      'field_description' => "Nice english description!",
    ];

    $this->assertEquals("Nice english description!", $entity->field_reference_override->entity->field_description->value);

    $translation = $entity->getTranslation('de');
    $this->assertEquals("Main description", $translation->field_reference_override->entity->field_description->value);

    $translation->field_reference_override->overwritten_property_map = [
      'field_description' => "Nice german description!",
    ];
    $translation->save();
    $this->assertEquals("Nice german description!", $translation->field_reference_override->entity->field_description->value);
  }

  /**
   * Test with translatable parent entity and a translatable reference.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTranslatableParentWithTranslatableReference() {
    $referenced_entity = EntityTestMul::create([
      'name' => 'Referenced entity',
      'field_description' => 'Main english description',
      'langcode' => 'en',
    ]);
    $referenced_entity->save();
    $translation = $referenced_entity->addTranslation('de', $referenced_entity->toArray());
    $translation->field_description = 'Main german description';
    $translation->save();

    // Create english parent entity.
    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_reference_override' => [
        'target_id' => $referenced_entity->id(),
      ],
      'langcode' => 'en',
    ]);
    $entity->save();

    // Add german translation.
    $entity->addTranslation('de', $entity->toArray());
    $entity->save();

    $entity->field_reference_override->overwritten_property_map = [
      'field_description' => "Nice english description!",
    ];

    $this->assertEquals("Nice english description!", $entity->field_reference_override->entity->field_description->value);

    $translation = $entity->getTranslation('de');
    $this->assertEquals("Main english description", $translation->field_reference_override->entity->field_description->value);
    $referenced_translation = $translation->field_reference_override->entity->getTranslation('de');
    $this->assertEquals("Main german description", $referenced_translation->field_description->value);

    $translation->field_reference_override->overwritten_property_map = [
      'field_description' => "Nice german description!",
    ];
    $translation->save();
    $referenced_translation = $translation->field_reference_override->entity->getTranslation('de');
    $this->assertEquals("Nice german description!", $referenced_translation->field_description->value);
  }

}
