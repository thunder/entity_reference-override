<?php

namespace Drupal\entity_reference_override;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_reference_override\Form\OverrideEntityForm;
use Drupal\media_library\MediaLibraryState;

class OverrideFormBuilder {

  use StringTranslationTrait;

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
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityDisplayRepositoryInterface $entityDisplayRepository, EntityFieldManagerInterface $entityFieldManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityDisplayRepository = $entityDisplayRepository;
    $this->entityFieldManager = $entityFieldManager;
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
  public function buildForm(EntityReferenceOverrideState $state = NULL) {

    if (!$state) {
      $state = EntityReferenceOverrideState::fromRequest(\Drupal::request());
    }

    $form_state = new FormState();
    $form_state->set('entity_reference_override', $state);

    return \Drupal::formBuilder()->buildForm(OverrideEntityForm::class, $form_state);
  }

}
