<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Implementation of the 'entity_reference_autocomplete_with_override' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_autocomplete_with_override",
 *   label = @Translation("Autocomplete (with override)"),
 *   description = @Translation("An autocomplete text field with overrides"),
 *   field_types = {
 *     "entity_reference_override"
 *   }
 * )
 */
class EntityReferenceAutocompleteWithOverrideWidget extends EntityReferenceAutocompleteWidget {

  use EntityReferenceOverrideWidgetTrait {
    formElement as traitFormElement;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $field_state = static::getWidgetState($form['#parents'], $this->fieldDefinition->getName(), $form_state);
    if (isset($field_state['items'])) {
      $items->setValue($field_state['items']);
    }
    return parent::form($items, $form, $form_state, $get_delta);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = $this->traitFormElement($items, $delta, $element, $form, $form_state);

    $element['target_id']['#ajax'] = [
      'callback' => [static::class, 'rebuildAutocompleteWidget'],
      'event' => 'autocompleteclose change',
    ];
    // Workaround for IEF. Without, ::extractFormValues() is not executed.
    $element['target_id']['#ief_submit_trigger'] = TRUE;
    $element['edit']['#weight'] = $element['target_id']['#weight'];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $element = parent::formMultipleElements($items, $form, $form_state);
    $element['#attached']['library'] = ['entity_reference_override/widget.entity_reference_autocomplete_with_override'];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    parent::extractFormValues($items, $form, $form_state);

    $field_name = $this->fieldDefinition->getName();
    $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
    $field_state['items'] = $items->getValue();
    static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
  }

  /**
   * Rebuild autocomplete widget for ajax.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public static function rebuildAutocompleteWidget(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $triggering_element = $form_state->getTriggeringElement();

    $parents = array_slice($triggering_element['#array_parents'], 0, -2);
    $element = NestedArray::getValue($form, $parents);

    $response->addCommand(new ReplaceCommand('[data-drupal-selector="' . $element['#attributes']['data-drupal-selector'] . '"]', $element));

    return $response;
  }

}
