<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldType;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Plugin implementation of the 'media' field type.
 *
 * @FieldType(
 *   id = "entity_reference_override",
 *   label = @Translation("Entity reference override"),
 *   description = @Translation("An entity field containing an entity reference and additional data."),
 *   category = @Translation("Reference"),
 *   default_widget = "entity_reference_autocomplete",
 *   default_formatter = "entity_reference_label",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList"
 * )
 */
class EntityReferenceOverrideItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['overwritten_property_map'] = MapDataDefinition::create()
      ->setLabel(t('Overwritten property map'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['overwritten_property_map'] = [
      'description' => 'A map to overwrite media data per instance.',
      'type' => 'blob',
      'size' => 'big',
      'serialize' => TRUE,
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    if ($name == 'entity' && !empty($this->values['overwritten_property_map'])) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = parent::__get('entity');

      $this->overwriteFields($entity, $this->values['overwritten_property_map']);
      $translation = $entity->getTranslation($this->getLangcode());
      $this->overwriteFields($translation, $this->values['overwritten_property_map']);

      $entity->addCacheableDependency($this->getEntity());
      $entity->overwritten = TRUE;
      return $entity;
    }
    return parent::__get($name);
  }

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

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    if (empty($this->values['overwritten_property_map'])) {
      $this->values['overwritten_property_map'] = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getPreconfiguredOptions() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'overwritable_properties' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::fieldSettingsForm($form, $form_state);

    $form['overwritable_properties'] = [
      '#type' => 'details',
      '#title' => $this->t('Overwritable properties'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo */
    $entityTypeBundleInfo = \Drupal::service('entity_type.bundle.info');

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $target_type = $this->getSetting('target_type');
    foreach ($entityTypeBundleInfo->getBundleInfo($target_type) as $bundle_id => $bundle) {
      $form['overwritable_properties'][$bundle_id] = [
        '#type' => 'details',
        '#title' => $bundle_id,
      ];

      $options = [];
      foreach ($entityFieldManager->getFieldDefinitions($target_type, $bundle_id) as $field_name => $definition) {
        if ($definition->isDisplayConfigurable('form')) {
          $options[$field_name] = $definition->getLabel();
        }
      }

      $form['overwritable_properties'][$bundle_id]['options'] = [
        '#type' => 'checkboxes',
        '#options' => $options,
        '#default_value' => $this->getSetting('overwritable_properties')[$bundle_id]['options'],
      ];

    }
    return $form;
  }

}
