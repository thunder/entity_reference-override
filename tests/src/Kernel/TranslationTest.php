<?php

namespace Drupal\Tests\entity_reference_override\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;

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

  }

  /**
   * Test translated overwritten metadata.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTranslatableOverwrittenMetadata() {
    for ($i = 0; $i < 3; ++$i) {
      $language_id = 'l' . $i;
      ConfigurableLanguage::create([
        'id' => $language_id,
        'label' => $this->randomString(),
      ])->save();
    }
    $available_langcodes = array_keys($this->container->get('language_manager')
      ->getLanguages());

    $referenced_entity = EntityTestMul::create([
      'name' => 'Referenced entity',
      'field_description' => 'Main description',
    ]);

    foreach ($available_langcodes as $langcode) {
      $translation = $referenced_entity->hasTranslation($langcode) ? $referenced_entity->getTranslation($langcode) : $referenced_entity->addTranslation($langcode, $referenced_entity->toArray());
      $translation->setName("Name $langcode");
    }
    $referenced_entity->save();

    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_reference_override' => $referenced_entity,
      'langcode' => reset($available_langcodes),
    ]);

    foreach ($available_langcodes as $langcode) {
      $translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity->addTranslation($langcode, $entity->toArray());
      $translation->field_reference_override->overwritten_property_map = [
        'field_description' => "Nice $langcode description!",
      ];
      $translation->save();
    }
    $entity->save();

    foreach ($available_langcodes as $langcode) {
      $translation = $entity->getTranslation($langcode);
      $reference_translation = $translation->field_reference_override->entity->getTranslation($langcode);

      $this->assertEquals("Name $langcode", $reference_translation->getName());
      $this->assertEquals("Nice $langcode description!", $reference_translation->field_description->value);
    }
  }

}
