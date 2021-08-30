<?php

namespace Drupal\entity_reference_override\Commands;

use Drush\Commands\DrushCommands;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\WidgetPluginManager;

/**
 * Drush commands for entity_reference_override.
 */
class MigrateCommands extends DrushCommands {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The key value service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValue;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity last installed schema repository service.
   *
   * @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface
   */
  protected $entityLastInstalledSchemaRepository;

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The widget plugin manager.
   *
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected $widgetPluginManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValue
   *   The key value service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository
   *   The entity last installed schema repository service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository service.
   * @param \Drupal\Core\Field\WidgetPluginManager $widgetPluginManager
   *   The widget plugin manager.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $configFactory, KeyValueFactoryInterface $keyValue, EntityFieldManagerInterface $entityFieldManager, EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository, EntityDisplayRepositoryInterface $entityDisplayRepository, WidgetPluginManager $widgetPluginManager) {
    $this->database = $database;
    $this->configFactory = $configFactory;
    $this->keyValue = $keyValue;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityLastInstalledSchemaRepository = $entityLastInstalledSchemaRepository;
    $this->entityDisplayRepository = $entityDisplayRepository;
    $this->widgetPluginManager = $widgetPluginManager;
  }

  /**
   * Migrates an entity_reference field to entity_reference_override.
   *
   * @usage drush entity_reference_override:migrate
   *   Migrates an entity_reference field to entity_reference_override.
   *
   * @command entity_reference_override:migrate
   */
  public function migrate() {

    $property_path = 'node.field_media';
    list($entity_type_id, $field_name) = explode('.', $property_path);

    $field_storage_config = $this->configFactory->getEditable('field.storage.' . $property_path);

    if ($field_storage_config->get('type') !== 'entity_reference') {
      $this->io()->error(\dt('Not an entity reference field'));
      return;
    }

    $schema_spec = [
      'description' => 'A map to overwrite entity data per instance.',
      'type' => 'text',
      'size' => 'big',
    ];

    $this->database->schema()->addField($entity_type_id . '__' . $field_name, $field_name . '_overwritten_property_map', $schema_spec);
    $this->database->schema()->addField($entity_type_id . '_revision__' . $field_name, $field_name . '_overwritten_property_map', $schema_spec);

    $store = $this->keyValue->get("entity.storage_schema.sql");
    $data = $store->get("$entity_type_id.field_schema_data.$field_name");
    $data["{$entity_type_id}__$field_name"]['fields']["{$field_name}_overwritten_property_map"] = $schema_spec;
    $data["{$entity_type_id}_revision__$field_name"]['fields']["{$field_name}_overwritten_property_map"] = $schema_spec;
    $store->set("$entity_type_id.field_schema_data.$field_name", $data);

    $schema_definitions = $this->entityLastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id);
    $schema_definitions[$field_name]->set('type', 'entity_reference_override');
    $this->entityLastInstalledSchemaRepository->setLastInstalledFieldStorageDefinitions($entity_type_id, $schema_definitions);

    $this->entityFieldManager->clearCachedFieldDefinitions();

    $field_storage_config->set('type', 'entity_reference_override');
    $field_storage_config->save(TRUE);

    FieldStorageConfig::loadByName($entity_type_id, $field_name)->calculateDependencies()->save();

    // Use the default widget and settings.
    $component = $this->widgetPluginManager->prepareConfiguration('entity_reference_override', []);

    $field_map = $this->entityFieldManager->getFieldMapByFieldType('entity_reference')[$entity_type_id][$field_name];
    foreach ($field_map['bundles'] as $bundle) {
      $field_config = $this->configFactory->getEditable('field.field.' . $bundle . '.' . $property_path);
      $field_config->set('field_type', 'entity_reference_override');
      $field_config->save();

      FieldConfig::loadByName($entity_type_id, $bundle, $field_name)->calculateDependencies()->save();

      $form_modes = $this->entityDisplayRepository->getFormModeOptionsByBundle($entity_type_id, $bundle);
      foreach (array_keys($form_modes) as $form_mode) {
        $form_display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, $form_mode);
        if ($form_display->getComponent($field_name)) {
          $form_display->setComponent($field_name, $component);
          $form_display->save();
        }
      }
    }

    $this->io()->success(\dt('Migration complete.'));
  }

}
