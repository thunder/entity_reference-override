<?php

namespace Drupal\Tests\entity_reference_override\FunctionalJavascript;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Base class for webdriver tests.
 */
class EntityReferenceOverrideTestBase extends WebDriverTestBase {

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
  protected $defaultTheme = 'stark';

  /**
   * Create an entity_reference_override field.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $field_name
   *   The field maschine name.
   * @param string $target_type
   *   The target entity type id.
   * @param string $target_bundle
   *   The target entity bundle id.
   * @param string $widget_type
   *   The widget type id.
   */
  protected function addReferenceOverrideField(string $entity_type, string $field_name, string $target_type, string $target_bundle, string $widget_type) {

    FieldStorageConfig::create([
      'field_name' => $field_name,
      'type' => 'entity_reference_override',
      'entity_type' => $entity_type,
      'cardinality' => -1,
      'settings' => [
        'target_type' => $target_type,
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $entity_type,
      'label' => $field_name,
      'settings' => [
        'handler_settings' => [
          'target_bundles' => [
            $target_bundle => $target_bundle,
          ],
        ],
      ],
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    $display_repository->getFormDisplay($entity_type, $entity_type, 'default')
      ->setComponent($field_name, [
        'type' => $widget_type,
        'settings' => [
          'form_mode' => 'overwrite',
        ],
      ])
      ->save();

    $display_repository->getViewDisplay($entity_type, $entity_type)
      ->setComponent($field_name, ['type' => 'entity_reference_entity_view'])
      ->save();

    $field_name = 'field_description';
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'type' => 'text_long',
      'entity_type' => $target_type,
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $target_type,
      'bundle' => $target_bundle,
      'label' => $field_name,
      'required' => TRUE,
    ])->save();

    $display_repository->getViewDisplay($target_type, $target_bundle)
      ->setComponent($field_name)
      ->save();

    EntityFormMode::create([
      'id' => $target_type . '.overwrite',
      'label' => 'Overwrite',
      'targetEntityType' => $target_type,
    ])->save();

    $display_repository->getFormDisplay($target_type, $target_bundle, 'overwrite')
      ->setComponent($field_name)
      ->save();
  }

}
