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
use Drupal\Core\Form\FormStateInterface;
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
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->setEntityDisplayRepository($container->get('entity_display.repository'));
    $form->setEntityFieldManager($container->get('entity_field.manager'));
    $form->setEntityTypeManager($container->get('entity_type.manager'));
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
   * Set the entity field manager service.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  protected function setEntityFieldManager(EntityFieldManagerInterface $entityFieldManager) {
    $this->entityFieldManager = $entityFieldManager;
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'override_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $entity_type = NULL, int $entity_id = NULL, string $field_name = NULL, $delta = NULL) {

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type)
      ->load($entity_id);

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity */
    $referenced_entity = $entity->{$field_name}->get($delta)->entity;

    $form_display = $this->entityDisplayRepository->getFormDisplay($referenced_entity->getEntityTypeId(), $referenced_entity->bundle());

    $definitions = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    $options = $definitions[$field_name]->getSetting('overwritable_properties')[$referenced_entity->bundle()]['options'];

    foreach ($form_display->getComponents() as $name => $component) {
      if (!in_array($name, array_filter($options))) {
        $form_display->removeComponent($name);
      }
    }

    $form_display->buildForm($referenced_entity, $form, $form_state);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    $form_state->set('entity_reference_override', [
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
      'field_name' => $field_name,
      'delta' => $delta,
    ]);

    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->isAjax()) {
      return;
    }

    $arguments = $form_state->get('entity_reference_override');

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($arguments['entity_type'])
      ->load($arguments['entity_id']);

    $entity->{$arguments['field_name']}->get($arguments['delta'])->overwritten_property_map = $this->getOverwrittenValues($form_state, $entity);
    $entity->save();

  }

  /**
   * Get overwritten values for an entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   *
   * @return string
   *   The overridden values as JSON.
   */
  protected function getOverwrittenValues(FormStateInterface $form_state, EntityInterface $entity) {
    $arguments = $form_state->get('entity_reference_override');

    /** @var \Drupal\Core\Entity\EntityInterface $referenced_entity */
    $referenced_entity = $entity->{$arguments['field_name']}->get($arguments['delta'])->entity;

    $definitions = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    $options = $definitions[$arguments['field_name']]->getSetting('overwritable_properties')[$referenced_entity->bundle()]['options'];

    $values = [];
    foreach ($options as $name => $enabled) {
      if ($enabled) {
        $values = [$name => $form_state->getValue($name)];
      }
    }
    return Json::encode($values);
  }

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

    $arguments = $form_state->get('entity_reference_override');

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($arguments['entity_type'])
      ->load($arguments['entity_id']);

    $values = $this->getOverwrittenValues($form_state, $entity);

    $selector = "[name=\"{$arguments['field_name']}[{$arguments['delta']}][overwritten_property_map]\"]";

    $response
      ->addCommand(new InvokeCommand($selector, 'val', [$values]))
      ->addCommand(new CloseDialogCommand());

    return $response;
  }

}
