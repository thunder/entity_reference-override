<?php
/**
 * @file
 * Contains
 */

namespace Drupal\entity_reference_override;


use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EntityReferenceOverrideState extends ParameterBag {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $parameters = []) {
   # $this->validateRequiredParameters($parameters['media_library_opener_id'], $parameters['media_library_allowed_types'], $parameters['media_library_selected_type'], $parameters['media_library_remaining']);
    #$parameters += [
    #  'media_library_opener_parameters' => [],
    #];
    parent::__construct($parameters);
    $this->set('hash', $this->getHash());
  }


  public static function create($entity_id, $entity_type, $field_name, $delta) {
    $state = new static([
      'entity_reference_override_entity_id' => $entity_id,
      'entity_reference_override_entity_type' => $entity_type,
      'entity_reference_override_field_name' => $field_name,
      'entity_reference_override_delta' => $delta,
    ]);
    return $state;
  }

  /**
   * Get the media library state from a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return static
   *   A state object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when the hash query parameter is invalid.
   */
  public static function fromRequest(Request $request) {
    $query = $request->query;

    // Create a EntityReferenceOverrideState object through the create method to make sure
    // all validation runs.
    $state = static::create(
      $query->get('entity_reference_override_entity_id'),
      $query->get('entity_reference_override_entity_type'),
      $query->get('entity_reference_override_field_name'),
      $query->get('entity_reference_override_delta')
    );

    // The request parameters need to contain a valid hash to prevent a
    // malicious user modifying the query string to attempt to access
    // inaccessible information.
    if (!$state->isValidHash($query->get('hash'))) {
      throw new BadRequestHttpException("Invalid media library parameters specified.");
    }

    // Once we have validated the required parameters, we restore the parameters
    // from the request since there might be additional values.
    $state->replace($query->all());
    return $state;
  }

  /**
   * Get the hash for the state object.
   *
   * @return string
   *   The hashed parameters.
   */
  public function getHash() {
    // Create a hash from the required state parameters and the serialized
    // optional opener-specific parameters. Sort the allowed types and
    // opener parameters so that differences in order do not result in
    // different hashes.

    $hash = implode(':', [
      $this->getEntityId(),
      $this->getEntityType(),
      $this->getFieldName(),
      $this->getDelta(),
    ]);

    return Crypt::hmacBase64($hash, \Drupal::service('private_key')->get() . Settings::getHashSalt());
  }

  /**
   * Validate a hash for the state object.
   *
   * @param string $hash
   *   The hash to validate.
   *
   * @return string
   *   The hashed parameters.
   */
  public function isValidHash($hash) {
    return hash_equals($this->getHash(), $hash);
  }

  public function getEntityId() {
    return $this->get('entity_reference_override_entity_id');
  }


  public function getEntityType() {
    return $this->get('entity_reference_override_entity_type');
  }


  public function getFieldName() {
    return $this->get('entity_reference_override_field_name');
  }


  public function getDelta() {
    return $this->get('entity_reference_override_delta');
  }
}
