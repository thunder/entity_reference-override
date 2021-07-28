<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'entity_reference_override_autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_override_autocomplete",
 *   label = @Translation("Autocomplete (with override)"),
 *   description = @Translation("An autocomplete text field with overrides"),
 *   field_types = {
 *     "entity_reference_override"
 *   }
 * )
 */
class EntityReferenceOverrideAutocompleteWidget extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $entity = $items->getEntity();
    $field_name = $this->fieldDefinition->getName();

    /** @var \Drupal\Core\Entity\EntityInterface $referencedEntity */
    $referencedEntity = $entity->{$field_name}->get($delta)->entity;
    if ($entity->isNew() || empty($referencedEntity)) {
      return $element;
    }

    $value = '';
    if (!empty($entity->{$field_name}->get($delta)->overwritten_property_map)) {
      $value = Json::encode($entity->{$field_name}->get($delta)->overwritten_property_map);
    }

    $element['overwritten_property_map'] = [
      '#type' => 'hidden',
      '#default_value' => $value,
    ];

    $dialog_options = [
      'minHeight' => '75%',
      'maxHeight' => '75%',
      'width' => '75%',
    ];

    $element['edit'] = [
      '#type' => 'link',
      '#title' => sprintf('Override %s in context of this %s',
        $referencedEntity->getEntityType()->getSingularLabel(),
        $entity->getEntityType()->getSingularLabel()),
      '#url' => Url::fromRoute('entity_reference_override.form', [
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => $entity->id(),
        'field_name' => $field_name,
        'delta' => $delta,
      ]),
      '#attributes' => [
        'class' => ['use-ajax', 'button'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode($dialog_options),
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state);
    foreach ($values as $key => $value) {
      if (!empty($value['overwritten_property_map'])) {
        $values[$key]['overwritten_property_map'] = Json::decode($value['overwritten_property_map']);
      }
      else {
        $values[$key]['overwritten_property_map'] = [];
      }
    }
    return $values;
  }

}
