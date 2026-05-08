<?php

namespace Drupal\Tests\trustarc\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\trustarc\Form\TrustarcSettingsForm;

/**
 * Kernel tests for TrustarcSettingsForm.
 *
 * Verifies that the settings form builds without exceptions and that every
 * library it references actually exists in the module's library registry.
 * This guards against the class of bug where a non-existent library name is
 * attached to the form, which would throw at render time on a live site.
 *
 * @group trustarc
 */
class TrustarcSettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['trustarc', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['trustarc']);
  }

  /**
   * Tests that the settings form builds without throwing an exception.
   *
   * Instantiates the form via the service container (the same path used at
   * runtime) and calls buildForm() directly. Any missing dependency, bad
   * service reference, or PHP error in the form definition will surface here
   * before it reaches a live Drupal site.
   */
  public function testSettingsFormBuildsWithoutException(): void {
    $form_object = TrustarcSettingsForm::create(\Drupal::getContainer());
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state);

    $this->assertIsArray($form);
    $this->assertArrayHasKey('cmp_script', $form);
    $this->assertArrayHasKey('cmp_version', $form);
  }

  /**
   * Tests that every library attached by the settings form exists.
   *
   * Iterates over the #attached library list produced by buildForm() and
   * queries the library discovery service for each one. A library string
   * referencing a name that does not exist in any enabled module's
   * .libraries.yml will cause this test to fail — catching the bug before
   * it reaches Drupal's renderer and throws an InvalidArgumentException.
   */
  public function testSettingsFormAttachedLibrariesExist(): void {
    $form_object = TrustarcSettingsForm::create(\Drupal::getContainer());
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state);

    $library_discovery = \Drupal::service('library.discovery');
    foreach ($form['#attached']['library'] ?? [] as $library) {
      [$extension, $name] = explode('/', $library, 2);
      $result = $library_discovery->getLibraryByName($extension, $name);
      $this->assertNotFalse(
        $result,
        "Library '$library' is attached to the settings form but is not defined in any .libraries.yml file."
      );
    }
  }

  /**
   * Tests that the form returns the expected form ID.
   */
  public function testSettingsFormId(): void {
    $form_object = TrustarcSettingsForm::create(\Drupal::getContainer());
    $this->assertSame('trustarc_settings_form', $form_object->getFormId());
  }

}
