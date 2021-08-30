<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'media_library_with_override_widget' widget.
 *
 * This plugin will only be made available if the media library module is
 * installed. This class will be replaced with
 * \Drupal\entity_reference_override\MediaLibraryWithOverrideWidget in
 * entity_reference_override_field_widget_info_alter().
 *
 * @FieldWidget(
 *   id = "media_library_with_override_widget",
 *   label = @Translation("Media library (with override)"),
 *   description = @Translation("Allows you to select items from the media library."),
 *   field_types = {
 *     "entity_reference_override"
 *   },
 *   multiple_values = TRUE,
 * )
 *
 * @see \Drupal\entity_reference_override\MediaLibraryWithOverrideWidget
 * @see entity_reference_override_field_widget_info_alter()
 */
class PlaceholderForMediaLibraryWithOverrideWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // This widget is a placeholder and will never be used.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This widget is a placeholder and will never be used.
    return FALSE;
  }

}
