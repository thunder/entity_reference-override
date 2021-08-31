<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;

/**
 * Plugin implementation of the 'image_image_without_actions' widget.
 *
 * @FieldWidget(
 *   id = "image_image_without_actions",
 *   label = @Translation("Image (without actions for override form)"),
 *   description = @Translation("Image widget without upload/remove buttons for the override form."),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageWithoutActionsWidget extends ImageWidget {

  /**
   * {@inheritdoc}
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);
    foreach (['upload_button', 'remove_button'] as $key) {
      $element[$key]['#access'] = FALSE;
    }
    return $element;
  }

}
