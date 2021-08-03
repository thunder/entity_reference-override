<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_reference_override\EntityReferenceOverrideState;
use Drupal\entity_reference_override\OverrideFormBuilder;
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

    if ($entity->isNew() || empty($items->referencedEntities()[$delta])) {
      return $element;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $referencedEntity */
    $referencedEntity = $items->referencedEntities()[$delta];


    $element['overwritten_property_map'] = [
      '#type' => 'hidden',
      '#default_value' => Json::encode($items->get($delta)->overwritten_property_map),
    ];

    $state = EntityReferenceOverrideState::create($entity->id(), $entity->getEntityTypeId(), $field_name, $delta, $items->get($delta)->overwritten_property_map);
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
          'message' => $this->t('Opening override form.'),
        ],
        // The AJAX system automatically moves focus to the first tabbable
        // element of the modal, so we need to disable refocus on the button.
        'disable-refocus' => TRUE,
      ],
    ];
    $element['edit']['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $element['update_widget'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update widget'),
      '#name' => 'entity_reference_override-update-' . $field_name . '-' . $delta,
      '#ajax' => [
        'callback' => [static::class, 'addItems1'],
      ],
      '#attributes' => [
        #'class' => ['js-hide'],
      ],
      '#submit' => [[static::class, 'addItems']],
    ];



    return $element;
  }

  public static function addItems1(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    return $response;
  }

  public static function addItems(array $form, FormStateInterface $form_state) {


    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -0));


    // Default to using the current selection if the form is new.
    $path = $element['#parents'];
    // We need to use the actual user input, since when #limit_validation_errors
    // is used, the unvalidated user input is not added to the form state.
    // @see FormValidator::handleErrorsWithLimitedValidation()
    $values = NestedArray::getValue($form_state->getUserInput(), $path);



    $field_state = static::getWidgetState($element['#array_parents'], 'field_media', $form_state);

  #  static::setWidgetState($element['#array_parents'], 'overwritten_property_map', $form_state, $field_state);

   # $form_state->gi

    $form_state->setRebuild();
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
