<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_reference_override\Form\OverrideEntityForm;

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
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    // Load the items for form rebuilds from the field state.
    $field_state = static::getWidgetState($form['#parents'], $this->fieldDefinition->getName(), $form_state);
    if (isset($field_state['items'])) {
      usort($field_state['items'], [SortArray::class, 'sortByWeightElement']);
      $items->setValue($field_state['items']);
    }
    return parent::form($items, $form, $form_state, $get_delta);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $entity = $items->getEntity();
    $field_name = $this->fieldDefinition->getName();

    if ($entity->isNew() || empty($items->referencedEntities()[$delta])) {
      return $element;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $referencedEntity */
    $referencedEntity = $items->referencedEntities()[$delta];

    $element['overwritten_property_map'] = [
      '#type' => 'hidden',
      '#default_value' => Json::encode($items->get($delta)->overwritten_property_map),
    ];

    $element['edit'] = [
      '#type' => 'button',
      '#name' => 'entity_reference_override-' . $field_name . '-' . $delta,
      '#value' => sprintf('Override %s in context of this %s',
        $referencedEntity->getEntityType()->getSingularLabel(),
        $entity->getEntityType()->getSingularLabel()),
      '#entity_reference_override_entity' => $items->get($delta)->entity,
      '#ajax' => [
        'callback' => [static::class, 'openOverrideForm'],
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Opening override form.'),
        ],
        // The AJAX system automatically moves focus to the first tabbable
        // element of the modal, so we need to disable refocus on the button.
        'disable-refocus' => TRUE,
      ],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    parent::extractFormValues($items, $form, $form_state);

    $button = $form_state->getTriggeringElement();
    if (!isset($button['#entity_reference_override_entity'])) {
      return;
    }

    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -2));
    $field_element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $delta = end($field_element['#parents']);

    // We need to use the actual user input, since when #limit_validation_errors
    // is used, the unvalidated user input is not added to the form state.
    // @see FormValidator::handleErrorsWithLimitedValidation()
    $overwritten_property_map = NestedArray::getValue($form_state->getUserInput(), array_merge_recursive($field_element['#parents'], ['overwritten_property_map']));

    $form_values = NestedArray::getValue($form_state->getValues(), $element['#parents']);
    $form_values[$delta]['overwritten_property_map'] = Json::decode($overwritten_property_map);
    unset($form_values['add_more']);

    $field_state = static::getWidgetState([], $this->fieldDefinition->getName(), $form_state);

    $field_state['items'] = $form_values;

    static::setWidgetState([], $this->fieldDefinition->getName(), $form_state, $field_state);
  }

  /**
   * Opens the override form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public static function openOverrideForm(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    $form_state = new FormState();
    $form_state->set('entity_reference_override_entity', $triggering_element['#entity_reference_override_entity']);
    $override_form = \Drupal::formBuilder()->buildForm(OverrideEntityForm::class, $form_state);

    $dialog_options = [
      'dialogClass' => 'media-library-widget-modal',
      'title' => t('Override'),
      'minHeight' => '75%',
      'maxHeight' => '75%',
      'width' => '75%',
    ];

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
