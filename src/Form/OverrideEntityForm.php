<?php

namespace Drupal\entity_reference_override\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements an example form.
 */
class OverrideEntityForm extends FormBase {

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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->setEntityDisplayRepository($container->get('entity_display.repository'));
    $form->setPrivateTempStore($container->get('tempstore.private'));
    $form->setEntityTypeManager($container->get('entity_type.manager'));
    $form->setEntityFieldManager($container->get('entity_field.manager'));
    return $form;
  }

  /**
   * Set the entity display repository service.
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
   * Set the entity type manager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  protected function setEntityTypeManager(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Set the entity field manager service.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  protected function setEntityFieldManager(EntityFieldManagerInterface $entityFieldManager) {
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'override_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $hash = $this->getRequest()->query->get('hash');

    $store_entry = $this->tempStore->get($hash);
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity */
    $referenced_entity = $store_entry['referenced_entity'];
    $form_mode = $store_entry['form_mode'];
    $referencing_entity_type_id = $store_entry['referencing_entity_type_id'];

    $form['#attached']['library'][] = 'entity_reference_override/form';

    // @todo Remove the ID when we can use selectors to replace content via
    //   AJAX in https://www.drupal.org/project/drupal/issues/2821793.
    $form['#prefix'] = '<div id="entity-reference-override-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -1000,
    ];

    $referencing_entity_type_label = $this->entityTypeManager->getDefinition($referencing_entity_type_id)->getSingularLabel();
    $form['help_text'] = [
      '#type' => 'item',
      '#markup' => $this->t('All changes made in here, only apply to this %entity_type that is referenced in the context of the parent %referencing_entity_type_label.', [
        '%entity_type' => $referenced_entity->getEntityType()->getSingularLabel(),
        '%referencing_entity_type_label' => $referencing_entity_type_label,
      ]),
      '#weight' => -1,
    ];

    $form_display = $this->getFormDisplay($referenced_entity, $form_mode);
    if ($form_display->isNew()) {
      $this->messenger()->addWarning($this->t('Form display mode %form_mode does not exists.', ['%form_mode' => $form_display->id()]));
      return $form;
    }
    $form_display->buildForm($referenced_entity, $form, $form_state);
    foreach (Element::children($form) as $key) {
      // Entity keys can be displayed, but are not overridable.
      $entity_type_keys = $referenced_entity->getEntityType()->getKeys();
      if (in_array($key, $entity_type_keys)) {
        $form[$key]['#disabled'] = TRUE;
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'url' => Url::fromRoute('entity_reference_override.form'),
        'options' => [
          'query' => $this->getRequest()->query->all() + [
            'hash' => $hash,
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Get overwrite form display for the referenced entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $referenced_entity
   *   The referenced entity.
   * @param string $form_mode
   *   The form mode of the display.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The overwrite form display.
   */
  protected function getFormDisplay(EntityInterface $referenced_entity, string $form_mode) {
    $form_display = $this->entityDisplayRepository->getFormDisplay($referenced_entity->getEntityTypeId(), $referenced_entity->bundle(), $form_mode);
    $definitions = $this->entityFieldManager->getFieldDefinitions($referenced_entity->getEntityTypeId(), $referenced_entity->bundle());
    foreach ($form_display->getComponents() as $name => $component) {
      if (!empty($definitions[$name]) && !$definitions[$name]->isDisplayConfigurable('form')) {
        $form_display->removeComponent($name);
      }
    }
    return $form_display;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * The access function.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function access() {
    return AccessResult::allowed();
  }

  /**
   * Submit form dialog #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that display validation error messages or represents a
   *   successful submission.
   */
  public function ajaxSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#entity-reference-override-form-wrapper', $form));
      return $response;
    }

    $hash = $this->getRequest()->query->get('hash');

    $store_entry = $this->tempStore->get($hash);
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity */
    $referenced_entity = $store_entry['referenced_entity'];
    $form_mode = $store_entry['form_mode'];
    $field_widget_id = $store_entry['field_widget_id'];

    $values = [];
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $original_entity */
    $original_entity = $this->entityTypeManager->getStorage($referenced_entity->getEntityTypeId())->load($referenced_entity->id());
    foreach ($this->fillEntityWithValues($referenced_entity, $original_entity, $form_mode, $form, $form_state) as $name) {
      if (!$referenced_entity->get($name)->equals($original_entity->get($name))) {
        $values[$name] = $referenced_entity->get($name)->getValue();
      }
    }

    $response
      ->addCommand(new InvokeCommand("[data-entity-reference-override-value=\"$field_widget_id\"]", 'val', [Json::encode($values)]))
      ->addCommand(new InvokeCommand("[data-entity-reference-override-update=\"$field_widget_id\"]", 'trigger', ['mousedown']))
      ->addCommand(new CloseDialogCommand());

    return $response;
  }

  /**
   * Fills the referenced entity with the values of the submitted form.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity
   *   The referenced entity.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $original_entity
   *   The original entity.
   * @param string $form_mode
   *   The form mode of the display.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The processed field items.
   */
  protected function fillEntityWithValues(FieldableEntityInterface $referenced_entity, FieldableEntityInterface $original_entity, string $form_mode, array $form, FormStateInterface $form_state) {
    $form_display = $this->getFormDisplay($referenced_entity, $form_mode);
    $extracted_fields = $form_display->extractFormValues($referenced_entity, $form, $form_state);

    // Merge in default values.
    foreach ($extracted_fields as $field) {
      $original_field_value = $original_entity->get($field)->getValue();
      $new_field_value = $referenced_entity->get($field)->getValue();
      $merged_field_value = [];
      foreach ($new_field_value as $key => $value) {
        $merged_field_value[$key] = array_merge($original_field_value[$key] ?? [], $value);
      }
      $referenced_entity->set($field, $merged_field_value);
    }

    return $extracted_fields;
  }

}
