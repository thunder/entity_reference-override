<?php

namespace Drupal\entity_reference_override\Commands;

use Drush\Commands\DrushCommands;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Drush commands for entity_reference_override.
 */
class MigrateCommands extends DrushCommands {

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

    $configFactory = \Drupal::configFactory();
    /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $lastInstalledSchemaRepository */
    $lastInstalledSchemaRepository = \Drupal::service('entity.last_installed_schema.repository');

    $database = \Drupal::database();

    /** @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValue */
    $keyValue = \Drupal::service('keyvalue');

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');

    $field_storage_config = $configFactory->getEditable('field.storage.' . $property_path);

    if ($field_storage_config->get('type') !== 'entity_reference') {
      $this->io()->error(\dt('Not an entity reference field'));
      return;
    }

    $schema_spec = [
      'description' => 'A map to overwrite entity data per instance.',
      'type' => 'blob',
      'size' => 'big',
      'serialize' => TRUE,
    ];
    $database->schema()->addField($entity_type_id . '__' . $field_name, $field_name . '_overwritten_property_map', $schema_spec);
    $database->schema()->addField($entity_type_id . '_revision__' . $field_name, $field_name . '_overwritten_property_map', $schema_spec);

    $store = $keyValue->get("entity.storage_schema.sql");
    $data = $store->get("$entity_type_id.field_schema_data.$field_name");
    $data["{$entity_type_id}__$field_name"]['fields']["{$field_name}_overwritten_property_map"] = $schema_spec;
    $data["{$entity_type_id}_revision__$field_name"]['fields']["{$field_name}_overwritten_property_map"] = $schema_spec;
    $store->set("$entity_type_id.field_schema_data.$field_name", $data);

    $schema_definitions = $lastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id);
    $schema_definitions[$field_name]->set('type', 'entity_reference_override');
    $lastInstalledSchemaRepository->setLastInstalledFieldStorageDefinitions($entity_type_id, $schema_definitions);

    $entityFieldManager->clearCachedFieldDefinitions();

    $field_storage_config->set('type', 'entity_reference_override');
    $field_storage_config->save(TRUE);

    FieldStorageConfig::loadByName($entity_type_id, $field_name)->calculateDependencies()->save();

    $field_map = $entityFieldManager->getFieldMapByFieldType('entity_reference')[$entity_type_id][$field_name];
    foreach ($field_map['bundles'] as $bundle) {
      $field_config = $configFactory->getEditable('field.field.' . $bundle . '.' . $property_path);
      $field_config->set('field_type', 'entity_reference_override');
      $field_config->save();

      FieldConfig::loadByName($entity_type_id, $bundle, $field_name)->calculateDependencies()->save();
    }

    $this->io()->success(\dt('Migration complete.'));
  }

}
