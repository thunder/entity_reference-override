<?php

namespace Drupal\entity_reference_override\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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

  use AjaxFormHelperTrait;

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

    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -1000,
    ];

    $form_display = $this->getFormDisplay($referenced_entity, $form_mode);
    if ($form_display->isNew()) {
      $this->messenger()->addWarning($this->t('Form display mode %form_mode does not exists.', ['%form_mode' => $form_display->id()]));
      return $form;
    }
    $form_display->buildForm($referenced_entity, $form, $form_state);

    // Build form for the original entity.
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $original_entity */
    $original_entity = $this->entityTypeManager->getStorage($referenced_entity->getEntityTypeId())->load($referenced_entity->id());
    $original_form = $form;
    $form_display->buildForm($original_entity, $original_form, $form_state);

    // Add overwrite checkbox to form elements.
    $this->modifyForm($form, $original_form);

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
   * Add override checkbox to form elements.
   *
   * @param array $form
   *   The current form.
   * @param array $original_form
   *   The form with the original values.
   */
  protected function modifyForm(array &$form, array $original_form) {

    foreach (Element::children($form) as $key) {
      if (isset($form[$key]['#access']) && !$form[$key]['#access']) {
        continue;
      }
      if (!($element = &$this->findFormElement($form[$key]))) {
        continue;
      }

      $original_element = $this->findFormElement($original_form[$key]);

      // Add the override checkbox to the form.
      $form[$key . '_override'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Override field value'),
        '#weight' => $form[$key]['#weight'] ?? 0,
        '#default_value' => $original_element['#default_value'] !== $element['#default_value'],
      ];

      // Modify the referenced element a bit so it doesn't cause errors.
      $element['#required'] = FALSE;

      // Disable form field until checkbox is checked.
      $form[$key]['#states'] = [
        'enabled' => [
          sprintf('[name="%s_override"]', $key) => ['checked' => TRUE],
        ],
      ];
    }
  }

  /**
   * Finds the deepest most form element and returns it.
   *
   * @param array $form
   *   The form element we're searching.
   * @param string $title
   *   The most recent non-empty title from previous form elements.
   *
   * @return array|null
   *   The deepest most element if we can find it.
   */
  protected function &findFormElement(array &$form, $title = NULL) {
    $element = NULL;
    foreach (Element::children($form) as $key) {
      // Not all levels have both #title and #type.
      // Attempt to inherit #title from previous iterations.
      // Some #titles are empty strings.  Ignore them.
      if (!empty($form[$key]['#title'])) {
        $title = $form[$key]['#title'];
      }
      elseif (!empty($form[$key]['title']['#value']) && !empty($form[$key]['title']['#type']) && $form[$key]['title']['#type'] === 'html_tag') {
        $title = $form[$key]['title']['#value'];
      }
      if (isset($form[$key]['#type']) && !empty($title)) {
        // Fix empty or missing #title in $form.
        if (empty($form[$key]['#title'])) {
          $form[$key]['#title'] = $title;
        }
        $element = &$form[$key];
        break;
      }
      elseif (is_array($form[$key])) {
        $element = &$this->findFormElement($form[$key], $title);
      }
    }
    return $element;
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
      if (!$definitions[$name]->isDisplayConfigurable('form')) {
        $form_display->removeComponent($name);
      }
    }
    return $form_display;
  }

  /**
   * Get extracted property path.
   *
   * @param \Drupal\Core\Entity\EntityInterface $referenced_entity
   *   The referenced entity.
   *
   * @return array
   *   Values are entity_type_id, bundle, field, delta.
   */
  protected function getExtractedPropertyPath(EntityInterface $referenced_entity) {
    [$entity_type, $field_name, $delta] = explode('.', $referenced_entity->entity_reference_override_property_path);
    [$entity_type_id, $bundle] = explode(':', $entity_type);
    return [$entity_type_id, $bundle, $field_name, $delta];
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
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $hash = $this->getRequest()->query->get('hash');

    $store_entry = $this->tempStore->get($hash);
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity */
    $referenced_entity = $store_entry['referenced_entity'];
    $form_mode = $store_entry['form_mode'];

    [, , $field_name, $delta] = $this->getExtractedPropertyPath($referenced_entity);

    $values = [];
    $form_display = $this->getFormDisplay($referenced_entity, $form_mode);
    foreach ($form_display->extractFormValues($referenced_entity, $form, $form_state) as $name) {
      if ($form_state->getValue($name . '_override')) {
        $values[$name] = $referenced_entity->get($name)->getValue();
      }
    }

    $selector = "[name=\"{$field_name}[$delta][overwritten_property_map]\"]";

    $response
      ->addCommand(new InvokeCommand($selector, 'val', [Json::encode($values)]))
      ->addCommand(new CloseDialogCommand());

    return $response;
  }

}
