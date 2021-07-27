<?php

namespace Drupal\Tests\entity_reference_override\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\media\Kernel\MediaKernelTestBase;

/**
 * Tests the access of field values with the media item.
 *
 * @group media
 */
class EntityReferenceOverrideItemTest extends MediaKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'language',
    'entity_reference_override',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');

    $field_name = 'field_media';
    $entity_type = 'entity_test';
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'type' => 'entity_reference_override',
      'entity_type' => $entity_type,
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'media',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $entity_type,
      'label' => $field_name,
    ])->save();
  }

  /**
   * Tests the overwrites for a media field.
   */
  public function testOverwrittenMetadata() {
    $mediaType = $this->createMediaType('file');
    $media = $this->generateMedia('test.patch', $mediaType);
    $media->save();

    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_media' => $media,
    ]);
    $entity->save();

    $this->assertEquals('Mr. Jones', $entity->field_media->entity->getName());
    $this->assertEquals('', $entity->field_media->entity->field_media_file->entity->description);
    $this->assertEquals(1, $entity->field_media->entity->field_media_file->entity->id());
    $this->assertEquals('test.patch', $entity->field_media->entity->field_media_file->entity->getFilename());

    $entity->field_media->overwritten_property_map = [
      'name' => 'Overwritten name',
      'field_media_file' => [['description' => 'Nice description!']],
    ];

    $this->assertEquals('Overwritten name', $entity->field_media->entity->getName());
    $this->assertEquals('Nice description!', $entity->field_media->entity->field_media_file->description);
    $this->assertEquals(1, $entity->field_media->entity->field_media_file->entity->id());
    $this->assertEquals('test.patch', $entity->field_media->entity->field_media_file->entity->getFilename());

    $entity->save();

    $this->assertEquals('Overwritten name', $entity->field_media->entity->getName());
    $this->assertEquals('Nice description!', $entity->field_media->entity->field_media_file->description);
    $this->assertEquals(1, $entity->field_media->entity->field_media_file->entity->id());
    $this->assertEquals('test.patch', $entity->field_media->entity->field_media_file->entity->getFilename());
  }

  /**
   * Tests the overwrites for a media field.
   */
  public function testMultivalueOverwrittenMetadata() {
    $mediaType = $this->createMediaType('file');

    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'type' => 'string',
      'entity_type' => 'media',
      'cardinality' => -1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'media',
      'bundle' => $mediaType->id(),
      'label' => 'field_text',
    ])->save();

    $media1 = $this->generateMedia('test.patch', $mediaType);
    $media1->field_text = 'Media Text 1';
    $media1->save();

    $media2 = $this->generateMedia('test1.patch', $mediaType);
    $media2->save();

    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_media' => [$media1, $media2],
    ]);
    $entity->save();

    $this->assertEquals('Mr. Jones', $entity->field_media->entity->getName());
    $this->assertEquals('', $entity->field_media->entity->field_media_file->entity->description);
    $this->assertEquals(1, $entity->field_media->entity->field_media_file->entity->id());
    $this->assertEquals('test.patch', $entity->field_media->entity->field_media_file->entity->getFilename());

    $this->assertEquals('Mr. Jones', $entity->field_media->get(1)->entity->getName());
    $this->assertEquals('', $entity->field_media->get(1)->entity->field_media_file->entity->description);
    // ID 3 is correct, ID 2 is the generic media icon.
    $this->assertEquals(3, $entity->field_media->get(1)->entity->field_media_file->entity->id());
    $this->assertEquals('test1.patch', $entity->field_media->get(1)->entity->field_media_file->entity->getFilename());

    $entity->field_media->get(0)->overwritten_property_map = [
      'name' => 'Overwritten name',
      'field_media_file' => [['description' => 'Nice description!']],
      'field_text' => [1 => ['value' => 'Overwritten Text 2']],
    ];
    $entity->field_media->get(1)->overwritten_property_map = [
      'name' => 'Overwritten name for media 2',
      'field_media_file' => [['description' => 'Nice description for media 2!']],
    ];
    $entity->save();

    $this->assertEquals('Overwritten name', $entity->field_media->get(0)->entity->getName());
    $this->assertEquals('Nice description!', $entity->field_media->get(0)->entity->field_media_file->description);
    $this->assertEquals(1, $entity->field_media->get(0)->entity->field_media_file->entity->id());
    $this->assertEquals('test.patch', $entity->field_media->get(0)->entity->field_media_file->entity->getFilename());
    $this->assertEquals('Media Text 1', $entity->field_media->get(0)->entity->field_text->get(0)->value);
    $this->assertEmpty($entity->field_media->get(0)->entity->field_text->get(1));

    $this->assertEquals('Overwritten name for media 2', $entity->field_media->get(1)->entity->getName());
    $this->assertEquals('Nice description for media 2!', $entity->field_media->get(1)->entity->field_media_file->get(0)->description);
    $this->assertEquals(3, $entity->field_media->get(1)->entity->field_media_file->entity->id());
    $this->assertEquals('test1.patch', $entity->field_media->get(1)->entity->field_media_file->entity->getFilename());
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
      file_put_contents('public://' . $language_id . '.png', '');
    }
    $available_langcodes = array_keys($this->container->get('language_manager')
      ->getLanguages());

    $mediaType = $this->createMediaType('file');
    $media = $this->generateMedia('test.patch', $mediaType);
    foreach ($available_langcodes as $langcode) {
      $translation = $media->hasTranslation($langcode) ? $media->getTranslation($langcode) : $media->addTranslation($langcode, $media->toArray());
      $translation->setName("Name $langcode");
    }
    $media->save();

    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_media' => $media,
      'langcode' => reset($available_langcodes),
    ]);

    foreach ($available_langcodes as $langcode) {
      $translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity->addTranslation($langcode, $entity->toArray());
      $translation->field_media->overwritten_property_map = [
        'field_media_file' => [['description' => "Nice $langcode description!"]],
      ];
      $translation->save();
    }
    $entity->save();

    foreach ($available_langcodes as $langcode) {
      $translation = $entity->getTranslation($langcode);
      $media_translation = $translation->field_media->entity->getTranslation($langcode);

      $this->assertEquals("Name $langcode", $media_translation->getName());
      $this->assertEquals("Nice $langcode description!", $media_translation->field_media_file->description);
      $this->assertEquals(1, $media_translation->field_media_file->entity->id());
      $this->assertEquals('test.patch', $media_translation->field_media_file->entity->getFilename());
    }

  }

}
