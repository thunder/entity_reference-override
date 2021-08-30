<?php

namespace Drupal\entity_reference_override;

use Drupal\Component\Utility\DiffArray;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;

/**
 * Service for re-usable functions.
 */
class EntityReferenceOverrideService {

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
