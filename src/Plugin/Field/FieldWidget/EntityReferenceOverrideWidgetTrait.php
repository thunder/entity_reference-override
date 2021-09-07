<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
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
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    if (!$this->handlesMultipleValues()) {
      $element = parent::formElement($items, $delta, $element, $form, $form_state);
    }

    $entity = $items->getEntity();
    $field_name = $this->fieldDefinition->getName();

    if (empty($items->referencedEntities()[$delta])) {
      return $element;
    }

    $parents = $form['#parents'];
    // Create an ID suffix from the parents to make sure each widget is unique.
    $id_suffix = $parents ? '-' . implode('-', $parents) : '';

    if ($form_state->getTriggeringElement()) {
      $items->get($delta)->overwritten_property_map = $form_state->getUserInput()[$field_name . '-' . $delta . '-entity-reference-override-map' . $id_suffix] ?? '{}';
    }

    $field_widget_id = implode(':', array_filter([
      $field_name . '-' . $delta,
      $id_suffix,
    ]));

    /** @var \Drupal\Core\Entity\EntityInterface $referenced_entity */
    $referenced_entity = $items->get($delta)->entity;
    if ($referenced_entity->hasTranslation($entity->language()->getId())) {
      $referenced_entity = $referenced_entity->getTranslation($entity->language()->getId());
    }

    $element['overwritten_property_map'] = [
      '#type' => 'hidden',
      '#name' => $field_name . '-' . $delta . '-entity-reference-override-map' . $id_suffix,
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
      ],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
      // Allow the override modal to be opened and saved even if there are form
      // errors for other fields.
      '#limit_validation_errors' => [array_merge($parents, [$field_name])],
      '#entity_reference_override' => [
        'referenced_entity' => $referenced_entity,
        'form_mode' => $this->getSetting('form_mode'),
        'field_widget_id' => $field_widget_id,
        'referencing_entity_type_id' => $entity->getEntityTypeId(),
        'ajax_commands' => [],
      ],
    ];

    return $element;
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
    $button = $form_state->getTriggeringElement();
    $override_form = \Drupal::formBuilder()->getForm(OverrideEntityForm::class, $button['#entity_reference_override']);
    $dialog_options = static::overrideFormDialogOptions();

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
