<?php

namespace Drupal\entity_reference_override_media_library\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_reference_override\Plugin\Field\FieldWidget\EntityReferenceOverrideWidgetTrait;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;

/**
 * Plugin implementation of the 'media_library_with_override_widget' widget.
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
 */
class MediaLibraryWithOverrideWidget extends MediaLibraryWidget {

  use EntityReferenceOverrideWidgetTrait {
    formElement as singleFormElement;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    foreach ($items->referencedEntities() as $delta => $media_item) {
      $element['selection'][$delta] += $this->singleFormElement($items, $delta, [], $form, $form_state);
      $element['selection'][$delta]['edit']['#attributes'] = [
        'class' => [
          'media-library-item__edit',
        ],
      ];
    }
    return $element;
  }

}
