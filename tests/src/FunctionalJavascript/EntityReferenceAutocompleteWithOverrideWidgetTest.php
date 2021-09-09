<?php

namespace Drupal\Tests\entity_reference_override\FunctionalJavascript;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;

/**
 * Tests with the media library override widget.
 */
class EntityReferenceAutocompleteWithOverrideWidgetTest extends EntityReferenceOverrideTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->addReferenceOverrideField('entity_test', 'field_reference_override', 'entity_test_mul', 'entity_test_mul', 'entity_reference_autocomplete_with_override');
  }

  /**
   * Test that overrides persists during multiple modal opens.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAutocompleteWidget() {
    $referenced_entity = EntityTestMul::create([
      'name' => 'Original name',
      'field_description' => [
        'value' => 'Original description',
        'format' => 'plain_text',
      ],
    ]);
    $referenced_entity->save();
    $entity = EntityTest::create();
    $entity->save();

    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test content',
      'access content',
      'view test entity',
    ]));

    $this->drupalGet($entity->toUrl('edit-form'));

    $autocomplete_field = $this->getSession()->getPage()->findField('field_reference_override' . '[0][target_id]');
    $autocomplete_field->setValue('Original name');
    $this->getSession()->getDriver()->keyDown($autocomplete_field->getXpath(), ' ');
    $this->assertSession()->waitOnAutocomplete();

  }

}
