<?php

namespace Drupal\entity_reference_override\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements an example form.
 */
class EditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $entity_type = NULL, int $entity_id = NULL, string $field_name  = NULL, $delta = NULL) {

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager */
    $fieldManager = \Drupal::service('entity_field.manager');

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displayRepository */
    $displayRepository = \Drupal::service('entity_display.repository');

    /** @var \Drupal\Core\Entity\EntityInterface $referenced_entity */
    $referenced_entity = $entity->{$field_name}->get($delta)->entity;

    $form_display = $displayRepository->getFormDisplay($referenced_entity->getEntityTypeId(), $referenced_entity->bundle());

    $definitions = $fieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    $options = $definitions[$field_name]->getSetting('overwritable_properties')[$referenced_entity->bundle()]['options'];

    foreach ($options as $name => $enabled) {
      if (!$enabled) {
        $form_display->removeComponent($name);

      }
    }

    $fake_entity = \Drupal::entityTypeManager()->getStorage($referenced_entity->getEntityTypeId())->create([$referenced_entity->getEntityType()->getKey('bundle') => $referenced_entity->bundle()]);

    $form_display->buildForm($fake_entity, $form, $form_state);


    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::submitForm'],
    ];

    $form['#entity_type'] = $entity_type;
    $form['#entity_id'] = $entity_id;
    $form['#field_name'] = $field_name;
    $form['#delta'] = $delta;


    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, string $entity_type = NULL, int $entity_id = NULL, string $field_name  = NULL, $delta = NULL) {

    $entity_type =$form['#entity_type'];
    $entity_id =$form['#entity_id'];
    $field_name =$form['#field_name'];
    $delta =$form['#delta'];

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);

    /** @var \Drupal\Core\Entity\EntityInterface $referenced_entity */
    $referenced_entity = $entity->{$field_name}->get($delta)->entity;

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager */
    $fieldManager = \Drupal::service('entity_field.manager');
    $definitions = $fieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    $options = $definitions[$field_name]->getSetting('overwritable_properties')[$referenced_entity->bundle()]['options'];

    foreach ($options as $name => $enabled) {
      if ($enabled) {
        $entity->{$field_name}->overwritten_property_map = [$name => $form_state->getValue($name)];


      }
    }

    $entity->save();

  }

  public static function access() {
    return AccessResult::allowed();
  }


}
