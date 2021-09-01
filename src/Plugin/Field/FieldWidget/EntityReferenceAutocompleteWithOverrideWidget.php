<?php

namespace Drupal\entity_reference_override\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;

/**
 * Implementation of the 'entity_reference_autocomplete_with_override' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_autocomplete_with_override",
 *   label = @Translation("Autocomplete (with override)"),
 *   description = @Translation("An autocomplete text field with overrides"),
 *   field_types = {
 *     "entity_reference_override"
 *   }
 * )
 */
class EntityReferenceAutocompleteWithOverrideWidget extends EntityReferenceAutocompleteWidget {

  use EntityReferenceOverrideWidgetTrait;

}
