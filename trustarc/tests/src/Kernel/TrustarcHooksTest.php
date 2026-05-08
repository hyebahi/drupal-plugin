<?php

namespace Drupal\Tests\trustarc\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for trustarc module hook implementations.
 *
 * Verifies that hook_page_attachments_alter() and hook_preprocess_html()
 * correctly inject the TrustArc script, attach drupalSettings, and add
 * the required config cache tag so pages are invalidated when settings change.
 *
 * These tests use a real Drupal kernel with the trustarc config installed.
 * A database is required; no browser is needed.
 *
 * @group trustarc
 */
class TrustarcHooksTest extends KernelTestBase {

  /**
   * Modules to enable for this test.
   *
   * 'system' provides the router and admin context services.
   * 'user' is required by the system module dependency chain.
   *
   * @var string[]
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
   * Tests that hook_page_attachments_alter() adds the config cache tag.
   *
   * The "config:trustarc.settings" cache tag must be present so that Drupal
   * automatically invalidates cached pages whenever TrustArc settings change.
   */
  public function testPageAttachmentsAlterAddsCacheTag(): void {
    \Drupal::configFactory()
      ->getEditable('trustarc.settings')
      ->set('cmp_script', 'example.com')
      ->save();

    $page = [];
    trustarc_page_attachments_alter($page);

    $this->assertArrayHasKey('#cache', $page);
    $this->assertContains('config:trustarc.settings', $page['#cache']['tags']);
  }

  /**
   * Tests that hook_page_attachments_alter() injects the TrustArc script tag.
   *
   * When a valid cmp_script is configured, an html_head element keyed
   * "trustarc_script" must be prepended to the page pointing to
   * consent.trustarc.com.
   */
  public function testPageAttachmentsAlterInjectsScript(): void {
    \Drupal::configFactory()
      ->getEditable('trustarc.settings')
      ->set('cmp_script', 'example.com')
      ->set('cmp_version', 'advanced')
      ->save();

    $page = [];
    trustarc_page_attachments_alter($page);

    $this->assertNotEmpty($page['#attached']['html_head']);
    [$element, $key] = $page['#attached']['html_head'][0];
    $this->assertSame('trustarc_script', $key);
    $this->assertStringContainsString('consent.trustarc.com', $element['#attributes']['src']);
  }

  /**
   * Tests that the hook does nothing when no script is set.
   *
   * If cmp_script is empty the hook must return early and leave the $page
   * array untouched, preventing an invalid script tag from being output.
   */
  public function testPageAttachmentsAlterSkipsWhenNoScript(): void {
    \Drupal::configFactory()
      ->getEditable('trustarc.settings')
      ->set('cmp_script', '')
      ->save();

    $page = [];
    trustarc_page_attachments_alter($page);

    $this->assertArrayNotHasKey('#attached', $page);
  }

  /**
   * Tests that hook_preprocess_html() adds the config cache tag.
   *
   * The "config:trustarc.settings" cache tag must also be added in the
   * preprocess hook so that the rendered HTML template is correctly
   * invalidated when settings change.
   */
  public function testPreprocessHtmlAddsCacheTag(): void {
    $variables = [];
    trustarc_preprocess_html($variables);

    $this->assertArrayHasKey('#cache', $variables);
    $this->assertContains('config:trustarc.settings', $variables['#cache']['tags']);
  }

  /**
   * Tests that hook_preprocess_html() populates drupalSettings.trustarc.
   *
   * The module configuration must be passed to JavaScript via drupalSettings
   * so that trustarc.js can read values such as the script ID, consent mode,
   * and Google Consent Mode options at runtime.
   */
  public function testPreprocessHtmlAttachesDrupalSettings(): void {
    \Drupal::configFactory()
      ->getEditable('trustarc.settings')
      ->set('cmp_script', 'example.com')
      ->save();

    $variables = [];
    trustarc_preprocess_html($variables);

    $this->assertArrayHasKey('trustarc', $variables['#attached']['drupalSettings']);
    $this->assertSame('example.com', $variables['#attached']['drupalSettings']['trustarc']['script']);
  }

  /**
   * Tests the script URL shape for the advanced CMP version.
   *
   * When cmp_version is "advanced" the injected script src must point to
   * /notice? with a domain query parameter equal to the configured script ID.
   */
  public function testPageAttachmentsAlterAdvancedUrlContainsDomain(): void {
    \Drupal::configFactory()
      ->getEditable('trustarc.settings')
      ->set('cmp_script', 'example.com')
      ->set('cmp_version', 'advanced')
      ->save();

    $page = [];
    trustarc_page_attachments_alter($page);

    $src = $page['#attached']['html_head'][0][0]['#attributes']['src'];
    $this->assertStringContainsString('/notice?', $src);
    $this->assertStringContainsString('domain=example.com', $src);
  }

  /**
   * Tests the script URL shape for the pro CMP version.
   *
   * When cmp_version is "pro" the injected script src must use the
   * /v2/notice/ path and must not include a domain= query parameter.
   */
  public function testPageAttachmentsAlterProVersionUrl(): void {
    \Drupal::configFactory()
      ->getEditable('trustarc.settings')
      ->set('cmp_script', 'example.com')
      ->set('cmp_version', 'pro')
      ->save();

    $page = [];
    trustarc_page_attachments_alter($page);

    $src = $page['#attached']['html_head'][0][0]['#attributes']['src'];
    $this->assertStringContainsString('/v2/notice/', $src);
    $this->assertStringContainsString('example.com', $src);
    $this->assertStringNotContainsString('domain=', $src);
  }

  /**
   * Tests that extra script parameters are appended to the script URL.
   *
   * When cmp_script_params is set the key-value pairs must appear in the
   * injected script src alongside the required domain parameter.
   */
  public function testPageAttachmentsAlterWithExtraScriptParams(): void {
    \Drupal::configFactory()
      ->getEditable('trustarc.settings')
      ->set('cmp_script', 'example.com')
      ->set('cmp_version', 'advanced')
      ->set('cmp_script_params', 'foo=bar')
      ->save();

    $page = [];
    trustarc_page_attachments_alter($page);

    $src = $page['#attached']['html_head'][0][0]['#attributes']['src'];
    $this->assertStringContainsString('foo=bar', $src);
    $this->assertStringContainsString('domain=example.com', $src);
  }

  /**
   * Tests that a script ID containing invalid characters blocks injection.
   *
   * If the stored cmp_script value does not match the allowed character
   * pattern the hook must return early and leave $page['#attached'] unset,
   * preventing a malformed URL from being output.
   */
  public function testPageAttachmentsAlterSkipsInvalidScriptChars(): void {
    \Drupal::configFactory()
      ->getEditable('trustarc.settings')
      ->set('cmp_script', 'bad script!')
      ->save();

    $page = [];
    trustarc_page_attachments_alter($page);

    $this->assertArrayNotHasKey('#attached', $page);
  }

  /**
   * Tests that hook_preprocess_html() attaches the trustarc JS library.
   *
   * The "trustarc/trustarc_js" library must appear in the variables
   * #attached library array so that trustarc.js is loaded on every
   * non-admin page.
   */
  public function testPreprocessHtmlAttachesLibrary(): void {
    $variables = [];
    trustarc_preprocess_html($variables);

    $this->assertContains('trustarc/trustarc_js', $variables['#attached']['library']);
  }

  /**
   * Tests that all expected drupalSettings keys are present.
   *
   * All fourteen configuration values must be passed to JavaScript so that
   * trustarc.js can function correctly regardless of which features are
   * enabled.
   */
  public function testPreprocessHtmlDrupalSettingsHasAllKeys(): void {
    $variables = [];
    trustarc_preprocess_html($variables);

    $settings = $variables['#attached']['drupalSettings']['trustarc'];
    $expected_keys = [
      'Version',
      'script',
      'scriptParams',
      'preferencesSelector',
      'preferences',
      'banner',
      'standardEventListener',
      'consentConfig',
      'gcmEnabled',
      'gaMeasurementID',
      'adsDataRedaction',
      'URLPassthrough',
      'impliedLocation',
      'consentTypeMapping',
    ];
    foreach ($expected_keys as $key) {
      $this->assertArrayHasKey($key, $settings, "drupalSettings.trustarc is missing key '$key'.");
    }
  }

}
