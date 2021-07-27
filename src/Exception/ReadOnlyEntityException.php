<?php

namespace Drupal\entity_reference_override\Exception;

/**
 * Thrown before saving of overridden entities.
 *
 * ReadOnlyEntityException should be thrown if an an entity referenced in the
 * 'entity_reference_override' field is saved.
 */
class ReadOnlyEntityException extends \LogicException {}
