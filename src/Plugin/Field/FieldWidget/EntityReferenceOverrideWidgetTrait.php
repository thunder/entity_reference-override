<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\entity_reference_override\Form\OverrideEntityForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\MessageCommand;

/**
 * Trait for widgets with entity_reference_override functionality.
 */
trait EntityReferenceOverrideWidgetTrait {

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The private temp store service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The private key service.
   *
   * @var \Drupal\Core\PrivateKey
   */
  protected $privateKey;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $widget = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $widget->setEntityDisplayRepository($container->get('entity_display.repository'));
    $widget->setPrivateTempStore($container->get('tempstore.private'));
    $widget->setPrivateKey($container->get('private_key'));
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
   * Set the temp store service.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   The temp store service.
   */
  protected function setPrivateTempStore(PrivateTempStoreFactory $privateTempStoreFactory) {
    $this->tempStore = $privateTempStoreFactory->get('entity_reference_override');
  }

  /**
   * Set the private key service.
   *
   * @param \Drupal\Core\PrivateKey $privateKey
   *   The private key service.
   */
  protected function setPrivateKey(PrivateKey $privateKey) {
    $this->privateKey = $privateKey;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'form_mode' => 'default',
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
    $summary[] = $this->t('Form mode: @form_mode', ['@form_mode' => $this->getSetting('form_mode')]);
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
    if (!$this->handlesMultipleValues()) {
      $element = parent::formElement($items, $delta, $element, $form, $form_state);
    }

    $entity = $items->getEntity();
    $field_name = $this->fieldDefinition->getName();

    if (empty($items->referencedEntities()[$delta])) {
      return $element;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $referenced_entity */
    $referenced_entity = $items->get($delta)->entity;
    if ($referenced_entity->hasTranslation($entity->language()->getId())) {
      $referenced_entity = $referenced_entity->getTranslation($entity->language()->getId());
    }

    $parents = $form['#parents'];
    // Create an ID suffix from the parents to make sure each widget is unique.
    $id_suffix = $parents ? '-' . implode('-', $parents) : '';
    $field_widget_id = implode(':', array_filter([
      $field_name . '-' . $delta,
      $id_suffix,
    ]));

    $hash = Crypt::hmacBase64($field_widget_id, Settings::getHashSalt() . $this->privateKey->get());
    $this->tempStore->set($hash, [
      'referenced_entity' => $referenced_entity,
      'form_mode' => $this->getSetting('form_mode'),
      'field_widget_id' => $field_widget_id,
      'referencing_entity_type_id' => $entity->getEntityTypeId(),
    ]);

    $element['overwritten_property_map'] = [
      '#type' => 'hidden',
      '#default_value' => $items->get($delta)->overwritten_property_map ?? '{}',
      '#attributes' => [
        'data-entity-reference-override-value' => $field_widget_id,
      ],
    ];

    $modal_title = $this->t('Override %entity_type in context of %bundle "%label"', [
      '%entity_type' => $referenced_entity->getEntityType()->getSingularLabel(),
      '%bundle' => ucfirst($entity->bundle()),
      '%label' => $entity->label(),
    ]);

    $limit_validation_errors = [array_merge($parents, [$field_name])];
    $element['edit'] = [
      '#type' => 'button',
      '#name' => $field_name . '-' . $delta . '-entity-reference-override-edit-button' . $id_suffix,
      '#value' => sprintf('Override %s in context of this %s',
        $referenced_entity->getEntityType()->getSingularLabel(),
        $entity->getEntityType()->getSingularLabel()),
      '#modal_title' => $modal_title,
      '#ajax' => [
        'callback' => [static::class, 'openOverrideForm'],
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Opening override form.'),
        ],
        'options' => [
          'query' => [
            'hash' => $hash,
          ],
        ],
      ],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
      // Allow the override modal to be opened and saved even if there are form
      // errors for other fields.
      '#limit_validation_errors' => $limit_validation_errors,
    ];

    // The hidden update button functionality was inspired by the media library.
    $wrapper_id = $field_name . '-entity-reference-override-wrapper' . $delta;
    $element['update_widget'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update widget'),
      '#name' => $field_name . '-' . $delta . '-entity-reference-override-update-button' . $id_suffix,
      '#ajax' => [
        'callback' => [static::class, 'updateOverrideWidget'],
        'wrapper' => $wrapper_id,
      ],
      '#attributes' => [
        'class' => ['js-hide'],
        'data-entity-reference-override-update' => $field_widget_id,
      ],
      '#submit' => [[static::class, 'updateOverrideFieldState']],
      // Ensure only the validation for this submit runs.
      '#limit_validation_errors' => $limit_validation_errors,
    ];

    return $element;
  }

  /**
   * Rebuild the widget form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public static function updateOverrideWidget(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $triggering_element = $form_state->getTriggeringElement();
    $wrapper_id = $triggering_element['#ajax']['wrapper'];

    $parents = array_slice($triggering_element['#array_parents'], 0, -2);
    $element = NestedArray::getValue($form, $parents);

    $response->addCommand(new ReplaceCommand("#$wrapper_id", $element));

    return $response;
  }

  /**
   * Update the field state.
   *
   * Read values from user input and pass them into the field state.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public static function updateOverrideFieldState(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();

    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -2));

    $user_input = NestedArray::getValue($form_state->getUserInput(), $element['#parents']);
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    foreach ($user_input as $key => $value) {
      $values[$key]['overwritten_property_map'] = $value['overwritten_property_map'] ?? '{}';
    }

    unset($values['add_more']);

    $field_state = static::getWidgetState($element['#field_parents'], $element['#field_name'], $form_state);
    $field_state['items'] = $values;
    static::setWidgetState($element['#field_parents'], $element['#field_name'], $form_state, $field_state);
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
    $override_form = \Drupal::formBuilder()->getForm(OverrideEntityForm::class);
    $dialog_options = static::overrideFormDialogOptions();
    $button = $form_state->getTriggeringElement();

    if (!OverrideEntityForm::access(\Drupal::currentUser())) {
      return (new AjaxResponse())
        ->addCommand(new MessageCommand(t("You don't have access to set overrides for this item."), NULL, ['type' => 'warning']));
    }

    return (new AjaxResponse())
      ->addCommand(new OpenModalDialogCommand($button['#modal_title'], $override_form, $dialog_options));
  }

  /**
   * Override form dialog options.
   *
   * @return array
   *   Options for the dialog.
   */
  protected static function overrideFormDialogOptions() {
    return [
      'minHeight' => '75%',
      'maxHeight' => '75%',
      'width' => '75%',
    ];
  }

}
