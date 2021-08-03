<?php
/**
 * @file
 * Contains
 */

namespace Drupal\entity_reference_override;


use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;

trait EntityReferenceOverrideTrait {

  /**
   * Override entity fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to override.
   * @param array $overwritten_property_map
   *   The new values.
   */
  protected function overwriteFields(EntityInterface $entity, array $overwritten_property_map) {
    foreach ($overwritten_property_map as $field_name => $field_value) {
      $values = $field_value;
      if (is_array($field_value)) {
        // Remove keys that don't exists in original entity.
        $field_value = array_intersect_key($field_value, $entity->get($field_name)->getValue());
        $values = NestedArray::mergeDeepArray([
          $entity->get($field_name)->getValue(),
          $field_value,
        ], TRUE);
      }
      $entity->set($field_name, $values);
    }
  }

}
