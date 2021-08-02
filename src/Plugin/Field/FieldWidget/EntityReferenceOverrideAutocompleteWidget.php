<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_reference_override\EntityReferenceOverrideState;
use Drupal\entity_reference_override\Form\OverrideEntityForm;
use Drupal\entity_reference_override\OverrideFormBuilder;
use Drupal\media_library\MediaLibraryUiBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;

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

    $state = EntityReferenceOverrideState::create($entity->id(), $entity->getEntityTypeId(), $field_name, $delta);
    $element['edit'] = [
      '#type' => 'button',
      '#name' => 'entity_reference_override-' . $field_name . '-' . $delta,
      '#value' => sprintf('Override %s in context of this %s',
        $referencedEntity->getEntityType()->getSingularLabel(),
        $entity->getEntityType()->getSingularLabel()),
      '#entity_reference_override_state' => $state,
      '#ajax' => [
        'callback' => [static::class, 'openOverrideForm'],
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Opening media library.'),
        ],
        // The AJAX system automatically moves focus to the first tabbable
        // element of the modal, so we need to disable refocus on the button.
        'disable-refocus' => TRUE,
      ],
    ];

    $element['edit']['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $element;
  }

  public static function openOverrideForm(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $override_form = \Drupal::service('entity_reference_override.form_builder')->buildForm($triggering_element['#entity_reference_override_state']);
    $dialog_options = OverrideFormBuilder::dialogOptions();
    return (new AjaxResponse())
      ->addCommand(new OpenModalDialogCommand($dialog_options['title'], $override_form, $dialog_options));
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
