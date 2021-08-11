<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_reference_override\Form\OverrideEntityForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $widget = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $widget->setEntityDisplayRepository($container->get('entity_display.repository'));
    return $widget;
  }

  /**
   * Set entity display repository service.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository service.
   */
  protected function setEntityDisplayRepository(EntityDisplayRepositoryInterface $entityDisplayRepository) {
    $this->entityDisplayRepository = $entityDisplayRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'form_mode' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form mode'),
      '#default_value' => $this->getSetting('form_mode'),
      '#description' => $this->t('The override form mode for referenced entities.'),
      '#options' => $this->entityDisplayRepository->getFormModeOptions($this->fieldDefinition->getSetting('target_type')),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = t('Form mode: @form_mode', ['@form_mode' => $this->getSetting('form_mode')]);
    return $summary;
  }

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
      '#entity_reference_override_referenced_entity' => $items->get($delta)->entity,
      '#ajax' => [
        'callback' => [static::class, 'openOverrideForm'],
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Opening override form.'),
        ],
        // The AJAX system automatically moves focus to the first tabbable
        // element of the modal, so we need to disable refocus on the button.
        'disable-refocus' => TRUE,
        'options' => [
          'query' => [
            'form_mode' => $this->getSetting('form_mode'),
          ],
        ],
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

    $field_state = static::getWidgetState($form['#parents'], $this->fieldDefinition->getName(), $form_state);
    $field_state['items'] = $items->getValue();
    static::setWidgetState($form['#parents'], $this->fieldDefinition->getName(), $form_state, $field_state);
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

    $override_form_state = new FormState();
    $override_form_state->set('entity_reference_override_referenced_entity', $triggering_element['#entity_reference_override_referenced_entity']);
    $override_form = \Drupal::formBuilder()->buildForm(OverrideEntityForm::class, $override_form_state);

    $dialog_options = static::overrideFormDialogOptions();

    return (new AjaxResponse())
      ->addCommand(new OpenModalDialogCommand($dialog_options['title'], $override_form, $dialog_options));
  }

  /**
   * Override form dialog options.
   *
   * @return array
   *   Options for the dialog.
   */
  protected static function overrideFormDialogOptions() {
    return [
      'title' => t('Override'),
      'minHeight' => '75%',
      'maxHeight' => '75%',
      'width' => '75%',
    ];
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
