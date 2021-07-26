<?php

namespace Drupal\entity_reference_override\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements an example form.
 */
class EditForm extends FormBase {

  use AjaxFormHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $entity_type = NULL, int $entity_id = NULL, string $field_name = NULL, $delta = NULL) {

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = \Drupal::entityTypeManager()
      ->getStorage($entity_type)
      ->load($entity_id);

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager */
    $fieldManager = \Drupal::service('entity_field.manager');

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displayRepository */
    $displayRepository = \Drupal::service('entity_display.repository');

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity */
    $referenced_entity = $entity->{$field_name}->get($delta)->entity;

    $form_display = $displayRepository->getFormDisplay($referenced_entity->getEntityTypeId(), $referenced_entity->bundle());

    $definitions = $fieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    $options = $definitions[$field_name]->getSetting('overwritable_properties')[$referenced_entity->bundle()]['options'];

    foreach ($options as $name => $enabled) {
      if (!$enabled) {
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

    $form['#entity_type'] = $entity_type;
    $form['#entity_id'] = $entity_id;
    $form['#field_name'] = $field_name;
    $form['#delta'] = $delta;

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

    $entity_type = $form['#entity_type'];
    $entity_id = $form['#entity_id'];

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = \Drupal::entityTypeManager()
      ->getStorage($entity_type)
      ->load($entity_id);

    $entity->{$field_name}->overwritten_property_map = $this->getOverwrittenValues($form, $form_state, $entity);
    $entity->save();

  }

  protected function getOverwrittenValues(array $form, FormStateInterface $form_state, EntityInterface $entity) {

    $field_name = $form['#field_name'];
    $delta = $form['#delta'];

    /** @var \Drupal\Core\Entity\EntityInterface $referenced_entity */
    $referenced_entity = $entity->{$field_name}->get($delta)->entity;

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager */
    $fieldManager = \Drupal::service('entity_field.manager');
    $definitions = $fieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    $options = $definitions[$field_name]->getSetting('overwritable_properties')[$referenced_entity->bundle()]['options'];

    $values = [];
    foreach ($options as $name => $enabled) {
      if ($enabled) {
        $values = [$name => $form_state->getValue($name)];
      }
    }
    return Json::encode($values);
  }

  public static function access() {
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $entity_type = $form['#entity_type'];
    $entity_id = $form['#entity_id'];
    $field_name = $form['#field_name'];
    $delta = $form['#delta'];

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = \Drupal::entityTypeManager()
      ->getStorage($entity_type)
      ->load($entity_id);

    $values = $this->getOverwrittenValues($form, $form_state, $entity);

    $selector = "[name=\"{$field_name}[$delta][overwritten_property_map]\"]";

    $response
      ->addCommand(new InvokeCommand($selector, 'val', [$values]))
      ->addCommand(new CloseDialogCommand());

    return $response;
  }
}
