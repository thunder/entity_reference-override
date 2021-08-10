<?php

namespace Drupal\entity_reference_override;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\entity_reference_override\Form\OverrideEntityForm;

/**
 * Builds the override form.
 */
class OverrideFormBuilder {

  use StringTranslationTrait;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The temp store service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(FormBuilderInterface $formBuilder, PrivateTempStoreFactory $privateTempStoreFactory, RequestStack $requestStack) {
    $this->formBuilder = $formBuilder;
    $this->tempStore = $privateTempStoreFactory->get('entity_reference_override');
    $this->request = $requestStack->getCurrentRequest();
  }

  /**
   * Get media library dialog options.
   *
   * @return array
   *   The media library dialog options.
   */
  public static function dialogOptions() {
    return [
      'dialogClass' => 'media-library-widget-modal',
      'title' => t('Override'),
      'minHeight' => '75%',
      'maxHeight' => '75%',
      'width' => '75%',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(EntityInterface $entity = NULL) {
    if (!$entity) {
      $entity = $this->tempStore->get($this->request->query->get('hash'));
    }

    $form_state = new FormState();
    $form_state->set('entity_reference_override_entity', $entity);
    return $this->formBuilder->buildForm(OverrideEntityForm::class, $form_state);
  }

}
