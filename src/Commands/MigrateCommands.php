<?php

namespace Drupal\entity_reference_override\Commands;

use Drush\Commands\DrushCommands;
use Drupal\entity_reference_override\EntityReferenceOverrideService;

/**
 * Drush commands for entity_reference_override.
 */
class MigrateCommands extends DrushCommands {

  /**
   * The entity reference override service.
   *
   * @var \Drupal\entity_reference_override\EntityReferenceOverrideService
   */
  protected $entityReferenceOverrideService;

  /**
   * Constructor.
   *
   * @param \Drupal\entity_reference_override\EntityReferenceOverrideService $entityReferenceOverrideService
   *   The entity reference override service.
   */
  public function __construct(EntityReferenceOverrideService $entityReferenceOverrideService) {
    parent::__construct();
    $this->entityReferenceOverrideService = $entityReferenceOverrideService;
  }

  /**
   * Migrates an entity_reference field to entity_reference_override.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The field name.
   *
   * @command entity_reference_override:migrate
   *
   * @usage drush entity_reference_override:migrate
   *   Migrates an entity_reference field to entity_reference_override.
   */
  public function migrate($entity_type_id, $field_name) {

    try {
      $this->entityReferenceOverrideService->migrateEntityReferenceField($entity_type_id, $field_name);
    }
    catch (\Exception $exception) {
      $this->io()->error($exception->getMessage());
    }

    $this->io()->success(\dt('Migration complete.'));
  }

}
