<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
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

    $hash = $this->getHash($form, $delta, $items->get($delta)->target_id);
    if (!$this->tempStore->get($hash)) {
      $this->tempStore->set($hash, [
        'referenced_entity' => $referenced_entity,
        'form_mode' => $this->getSetting('form_mode'),
        'referencing_entity_type_id' => $entity->getEntityTypeId(),
        'overwritten_property_map' => $items->get($delta)->overwritten_property_map,
      ]);
    }
    $element['hash'] = [
      '#value' => $hash,
      '#type' => 'value',
    ];

    $modal_title = $this->t('Override %entity_type in context of %bundle "%label"', [
      '%entity_type' => $referenced_entity->getEntityType()->getSingularLabel(),
      '%bundle' => ucfirst($entity->bundle()),
      '%label' => $entity->label(),
    ]);

    $parents = $form['#parents'];
    // Create an ID suffix from the parents to make sure each widget is unique.
    $id_suffix = $parents ? '-' . implode('-', $parents) : '';

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

    return $element;
  }

  /**
   * Calculate the hash for the temp store key.
   *
   * @param array $form
   *   The current form.
   * @param int $delta
   *   The field delta.
   * @param int $target_id
   *   The target id of the referenced entity.
   *
   * @return string
   *   The calculated hash.
   */
  protected function getHash(array $form, $delta, $target_id) {
    $parents = $form['#parents'];
    // Create an ID suffix from the parents to make sure each widget is unique.
    $id_suffix = $parents ? '-' . implode('-', $parents) : '';
    $field_widget_id = implode(':', array_filter([
      $this->fieldDefinition->getName() . '-' . $delta . '-' . $target_id,
      $id_suffix,
    ]));
    return Crypt::hmacBase64($field_widget_id, Settings::getHashSalt() . $this->privateKey->get());
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

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state);
    foreach ($values as $key => $value) {
      if (($store_entry = $this->tempStore->get($value['hash'])) && isset($store_entry['overwritten_property_map'])) {
        $values[$key]['overwritten_property_map'] = $store_entry['overwritten_property_map'];
      }
    }
    return $values;
  }

}
