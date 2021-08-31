<?php

namespace Drupal\entity_reference_override;

use Drupal\Component\Utility\DiffArray;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for re-usable functions.
 */
class EntityReferenceOverrideService {

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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $configFactory, KeyValueFactoryInterface $keyValue, EntityFieldManagerInterface $entityFieldManager, EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository, EntityDisplayRepositoryInterface $entityDisplayRepository, WidgetPluginManager $widgetPluginManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->database = $database;
    $this->configFactory = $configFactory;
    $this->keyValue = $keyValue;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityLastInstalledSchemaRepository = $entityLastInstalledSchemaRepository;
    $this->entityDisplayRepository = $entityDisplayRepository;
    $this->widgetPluginManager = $widgetPluginManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Migrates an entity_reference field to entity_reference_override.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The field name.
   */
  public function migrateEntityReferenceField($entity_type_id, $field_name) {
    $field_storage_config = $this->configFactory->getEditable("field.storage.$entity_type_id.$field_name");

    if ($field_storage_config->get('type') !== 'entity_reference') {
      throw new \Exception('Not an entity reference field');
    }

    /* @see \Drupal\entity_reference_override\Plugin\Field\FieldType\EntityReferenceOverrideItem::schema() */
    $schema_spec = [
      'description' => 'A map to overwrite entity data per instance.',
      'type' => 'text',
      'size' => 'big',
    ];

    $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type_id);

    $this->database->schema()->addField($entity_type_id . '__' . $field_name, $field_name . '_overwritten_property_map', $schema_spec);
    if ($entity_type_definition->isRevisionable()) {
      $this->database->schema()->addField($entity_type_id . '_revision__' . $field_name, $field_name . '_overwritten_property_map', $schema_spec);
    }

    $store = $this->keyValue->get("entity.storage_schema.sql");
    $data = $store->get("$entity_type_id.field_schema_data.$field_name");
    $data["{$entity_type_id}__$field_name"]['fields']["{$field_name}_overwritten_property_map"] = $schema_spec;
    if ($entity_type_definition->isRevisionable()) {
      $data["{$entity_type_id}_revision__$field_name"]['fields']["{$field_name}_overwritten_property_map"] = $schema_spec;
    }
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
      $field_config = $this->configFactory->getEditable("field.field.$entity_type_id.$bundle.$field_name");
      $field_config->set('field_type', 'entity_reference_override');
      $field_config->save();

      FieldConfig::loadByName($entity_type_id, $bundle, $field_name)->calculateDependencies()->save(TRUE);

      $form_modes = $this->entityDisplayRepository->getFormModeOptionsByBundle($entity_type_id, $bundle);
      foreach (array_keys($form_modes) as $form_mode) {
        $form_display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, $form_mode);
        if ($form_display->getComponent($field_name)) {
          $form_display->setComponent($field_name, $component);
          $form_display->save();
        }
      }
    }
  }

  /**
   * Returns the difference of given fields in two entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $referenced_entity
   *   The referenced entity.
   * @param \Drupal\Core\Entity\EntityInterface $original_entity
   *   The original entity.
   * @param array $fields
   *   The fields to compare.
   *
   * @return array
   *   The difference of the fields.
   */
  public function getOverriddenValues(EntityInterface $referenced_entity, EntityInterface $original_entity, array $fields) {
    $values = [];
    foreach ($fields as $field_name) {
      $original_field = $original_entity->get($field_name);

      // Merge in not defined keys of original field.
      $referenced_entity->set($field_name, NestedArray::mergeDeepArray([
        $original_field->getValue(),
        $referenced_entity->get($field_name)->getValue(),
      ], TRUE));

      if (!$referenced_entity->get($field_name)->equals($original_field)) {

        // Filter out values that won't be saved.
        $referenced_entity_values = array_map(function ($item) {
          return $item->toArray();
        }, $referenced_entity->get($field_name)->getIterator()->getArrayCopy());
        $values[$field_name] = DiffArray::diffAssocRecursive($referenced_entity_values, $original_field->getValue());
      }
    }
    return $values;
  }

}
