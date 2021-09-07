<?php

namespace Drupal\Tests\entity_reference_override\FunctionalJavascript;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\FunctionalJavascriptTests\SortableTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests with the media library override widget.
 */
class MediaLibraryWithOverrideWidgetTest extends EntityReferenceOverrideTestBase {

  use MediaTypeCreationTrait;
  use TestFileCreationTrait;
  use SortableTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
    'media_library',
  ];

  /**
   * The entity to act on.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $mediaType = $this->createMediaType('image');
    $this->addReferenceOverrideField('entity_test', 'field_reference_override', 'media', $mediaType->id(), 'media_library_with_override_widget');

    $this->entity = EntityTest::create();
    $this->entity->save();

    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test content',
      'administer media',
      'access content',
      'view test entity',
    ]));

    $medias = [];

    for ($i = 0; $i < 2; $i++) {
      $file = File::create([
        'uri' => $this->getTestFiles('image')[$i]->uri,
      ]);
      $file->save();
      $media = Media::create([
        'bundle' => $mediaType->id(),
        'field_media_image' => [
          [
            'target_id' => $file->id(),
            'alt' => 'default alt',
            'title' => 'default title',
          ],
        ],
      ]);
      $media->save();
      $medias[] = $media;
    }

    $this->drupalGet($this->entity->toUrl('edit-form'));
  }

  /**
   * Test edit form values after item re-order.
   */
  public function testEditFormAfterItemReOrder() {
    $this->addMediaItems([0, 1]);

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Override 1');
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->pressButton('Show media item weights');

    $this->getSession()->getPage()->fillField('field_reference_override[selection][0][weight]', '1');
    $this->getSession()->getPage()->fillField('field_reference_override[selection][1][weight]', '0');

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-1"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Override 2');
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-1"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 2', $modal);
    $this->getSession()->getPage()->find('css', '.ui-dialog .ui-dialog-titlebar-close')->click();

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Override 12');
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-1"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 2', $modal);
  }

  /**
   * Test edit form after multiple add items actions.
   */
  public function testEditFormAfterMultipleAddItems() {
    $this->addMediaItems([0]);
    $this->addMediaItems([1]);

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Override 1');
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 1', $modal);
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Save the entity.
    $this->getSession()->getPage()->pressButton('Save');
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('field_reference_override[selection][0][weight]', 0);
    $this->assertSession()->fieldValueEquals('field_reference_override[selection][1][weight]', 1);

    $this->assertSession()->elementExists('css', 'div[data-media-library-item-delta="0"] + div[data-media-library-item-delta="1"]');
    $this->sortableTo('div[data-media-library-item-delta="1"]', 'div[data-media-library-item-delta="1"]', 'div.js-media-library-selection');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementExists('css', 'div[data-media-library-item-delta="1"] + div[data-media-library-item-delta="0"]');
    $this->assertSession()->fieldValueEquals('field_reference_override[selection][0][weight]', 1);
    $this->assertSession()->fieldValueEquals('field_reference_override[selection][1][weight]', 0);

    // Check the override.
    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 1', $modal);
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // @todo Remove the return once
    //   https://www.drupal.org/project/drupal/issues/3115054 has been fixed.
    $this->markTestIncomplete(
      'Remove the return once https://www.drupal.org/project/drupal/issues/3115054 has been fixed.'
    );
    // This assertion currently fails.
    $this->assertSession()->elementExists('css', 'div[data-media-library-item-delta="1"] + div[data-media-library-item-delta="0"]');
  }

  /**
   * Test edit form after multiple add items actions.
   */
  public function testEditFormThenAddAndEditAgain() {
    $this->addMediaItems([0]);

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Override 1');
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->addMediaItems([1]);

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 1', $modal);
  }

  /**
   * Test saving an override and open the edit again.
   */
  public function testOnExistingEntity() {
    $this->addMediaItems([0]);

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Override 1');
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->pressButton('Save');

    $this->drupalGet($this->entity->toUrl('edit-form'));

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 1', $modal);
  }

  /**
   * Selects a number of items from the media library.
   *
   * @param array $indexes
   *   The indexes to select.
   */
  protected function addMediaItems(array $indexes) {
    $this->getSession()->getPage()->pressButton('Add media');
    $this->assertSession()->assertWaitOnAjaxRequest();
    foreach ($indexes as $index) {
      $this->getSession()->getPage()->checkField("media_library_select_form[$index]");
    }
    $this->assertSession()->elementExists('css', '.ui-dialog-buttonpane')->pressButton('Insert selected');
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * {@inheritdoc}
   */
  protected function sortableUpdate($item, $from, $to = NULL) {
    // See core/modules/media_library/js/media_library.widget.es6.js.
    $script = <<<JS
(function ($) {
    var selection = document.querySelectorAll('.js-media-library-selection');
    selection.forEach(function (widget) {
        $(widget).children().each(function (index, child) {
            $(child).find('.js-media-library-item-weight').val(index);
        });
    });
})(jQuery)

JS;

    $options = [
      'script' => $script,
      'args'   => [],
    ];

    $this->getSession()->getDriver()->getWebDriverSession()->execute($options);
  }

}
