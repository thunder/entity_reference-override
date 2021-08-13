<?php

namespace Drupal\entity_reference_override\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->setEntityDisplayRepository($container->get('entity_display.repository'));
    $form->setPrivateTempStore($container->get('tempstore.private'));
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
    $ignored_components = ['langcode', 'revision_log_message'];
    foreach ($ignored_components as $component) {
      $form_display->removeComponent($component);
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
    foreach ($this->getFormDisplay($referenced_entity, $form_mode)->getComponents() as $name => $component) {
      $values[$name] = $form_state->getValue($name);
    }

    $selector = "[name=\"{$field_name}[$delta][overwritten_property_map]\"]";

    $response
      ->addCommand(new InvokeCommand($selector, 'val', [Json::encode($values)]))
      ->addCommand(new CloseDialogCommand());

    return $response;
  }

}
