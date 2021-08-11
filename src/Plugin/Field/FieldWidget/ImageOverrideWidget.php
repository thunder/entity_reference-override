<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;

/**
 * Plugin implementation of the 'image_image_override' widget.
 *
 * @FieldWidget(
 *   id = "image_image_override",
 *   label = @Translation("Image (for override)"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageOverrideWidget extends ImageWidget {

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
