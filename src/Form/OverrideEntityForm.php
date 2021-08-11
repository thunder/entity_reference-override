<?php

namespace Drupal\entity_reference_override\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;
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
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

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
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->setEntityDisplayRepository($container->get('entity_display.repository'));
    $form->setEntityFieldManager($container->get('entity_field.manager'));
    $form->setPrivateTempStore($container->get('tempstore.private'));
    $form->setPrivateKey($container->get('private_key'));
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
  public function getFormId() {
    return 'override_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity */
    $referenced_entity = $form_state->get('entity_reference_override_referenced_entity');
    if (!$referenced_entity) {
      $token = $this->getRequest()->query->get('hash');
      $referenced_entity = $this->tempStore->get($token);
      $form_state->set('entity_reference_override_referenced_entity', $referenced_entity);
    }
    else {
      $token = Crypt::hmacBase64($referenced_entity->entity_reference_override_property_path, Settings::getHashSalt() . $this->privateKey->get());
      $this->tempStore->set($token, $referenced_entity);
    }

    $form_display = $this->entityDisplayRepository->getFormDisplay($referenced_entity->getEntityTypeId(), $referenced_entity->bundle());
    foreach ($form_display->getComponents() as $name => $component) {
      if (!in_array($name, $this->getOverwritableProperties($referenced_entity))) {
        $form_display->removeComponent($name);
      }
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
          'query' => [
            'hash' => $token,
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];

    return $form;
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
   * Get overwritable properties for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $referenced_entity
   *   The referencing entity.
   *
   * @return array
   *   Properties to override.
   */
  protected function getOverwritableProperties(EntityInterface $referenced_entity) {
    [$entity_type_id, $bundle, $field_name] = $this->getExtractedPropertyPath($referenced_entity);
    $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
    return array_filter($definitions[$field_name]->getSetting('overwritable_properties')[$referenced_entity->bundle()]['options'] ?? []);
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

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity */
    $referenced_entity = $form_state->get('entity_reference_override_referenced_entity');

    [, , $field_name, $delta] = $this->getExtractedPropertyPath($referenced_entity);

    $values = [];
    foreach ($this->getOverwritableProperties($referenced_entity) as $name) {
      $values[$name] = $form_state->getValue($name);
    }

    $selector = "[name=\"{$field_name}[$delta][overwritten_property_map]\"]";

    $response
      ->addCommand(new InvokeCommand($selector, 'val', [Json::encode($values)]))
      ->addCommand(new CloseDialogCommand());

    return $response;
  }

}
