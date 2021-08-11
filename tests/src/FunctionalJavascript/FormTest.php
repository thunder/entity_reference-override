<?php

namespace Drupal\Tests\entity_reference_override\FunctionalJavascript;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Form operation tests.
 */
class FormTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'field',
    'entity_test',
    'language',
    'text',
    'entity_reference_override',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $field_name = 'field_reference_override';
    $entity_type = 'entity_test';
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'type' => 'entity_reference_override',
      'entity_type' => $entity_type,
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'entity_test_mul',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $entity_type,
      'label' => $field_name,
      'settings' => [
        'overwritable_properties' => [
          'entity_test_mul' => [
            'options' => [
              'field_description' => 'field_description',
              'name' => 'name',
            ],
          ],
        ],
      ],
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    $display_repository->getFormDisplay($entity_type, $entity_type, 'default')
      ->setComponent($field_name)
      ->save();

    $display_repository->getViewDisplay($entity_type, $entity_type)
      ->setComponent($field_name, ['type' => 'entity_reference_entity_view'])
      ->save();

    $field_name = 'field_description';
    $entity_type = 'entity_test_mul';
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'type' => 'text_long',
      'entity_type' => $entity_type,
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $entity_type,
      'label' => $field_name,
    ])->save();

    $display_repository->getViewDisplay($entity_type, $entity_type)
      ->setComponent($field_name)
      ->save();

    $display_repository->getFormDisplay($entity_type, $entity_type)
      ->setComponent($field_name)
      ->save();
  }

  /**
   * Test that overrides persists during multiple modal opens.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSetOverride() {
    $referenced_entity = EntityTestMul::create([
      'name' => 'Original name',
      'field_description' => 'Original description',
    ]);
    $referenced_entity->save();
    $entity = EntityTest::create([
      'field_reference_override' => $referenced_entity,
    ]);
    $entity->save();

    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test content',
      'access content',
      'view test entity',
    ]));

    $this->drupalGet($entity->toUrl('edit-form'));

    $page = $this->getSession()->getPage();

    $page->pressButton('Override test entity - data table in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal = $page->find('css', '.ui-dialog');
    $modal->fillField('name[0][value]', 'Overridden name');
    $modal->fillField('field_description[0][value]', 'Overridden description');
    $page->find('css', '.ui-dialog button.form-submit')->click();

    // Open modal again to check if values persist.
    $page->pressButton('Override test entity - data table in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()
      ->fieldValueEquals('name[0][value]', 'Overridden name', $modal);
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Overridden description', $modal);
    $page->find('css', '.ui-dialog button.form-submit')->click();

    $page->pressButton('Save');

    $this->drupalGet($entity->toUrl());

    $this->assertSession()->pageTextContains('Overridden name');
    $this->assertSession()->pageTextContains('Overridden description');
  }

}
