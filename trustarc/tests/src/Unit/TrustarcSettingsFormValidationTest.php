<?php

namespace Drupal\Tests\trustarc\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\trustarc\Form\TrustarcSettingsForm;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for TrustarcSettingsForm validation logic.
 *
 * Tests the validateForm() method in isolation using mocked Drupal services.
 * No database or full Drupal bootstrap is required to run these tests.
 *
 * @group trustarc
 * @coversClass \Drupal\trustarc\Form\TrustarcSettingsForm
 */
class TrustarcSettingsFormValidationTest extends UnitTestCase {

  /**
   * The form under test.
   *
   * @var \Drupal\trustarc\Form\TrustarcSettingsForm
   */
  protected TrustarcSettingsForm $form;

  /**
   * Mocked form state used to capture validation errors.
   *
   * @var \Drupal\Core\Form\FormStateInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $formState;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturn('');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $logger = $this->createMock(LoggerInterface::class);
    $messenger = $this->createMock(MessengerInterface::class);

    $this->form = new TrustarcSettingsForm($configFactory, $logger, $messenger);
    $this->form->setStringTranslation($this->getStringTranslationStub());
    $this->formState = $this->createMock(FormStateInterface::class);
  }

  /**
   * Tests that a valid CMP script ID passes validation.
   *
   * A script ID containing only letters, numbers, dots, underscores, and
   * hyphens (e.g. "my-site.example.com") must not trigger any form errors.
   *
   * @covers ::validateForm
   */
  public function testValidCmpScriptPasses(): void {
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'my-site.example.com'],
      ['cmp_script_params', NULL, ''],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, 'consent_blackbar'],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, ''],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, []],
    ]);

    $this->formState->expects($this->never())->method('setErrorByName');

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that a CMP script ID containing invalid characters fails validation.
   *
   * Script IDs with spaces or special characters such as "bad script!" must
   * set a form error on the "cmp_script" field.
   *
   * @covers ::validateForm
   */
  public function testInvalidCmpScriptFails(): void {
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'bad script!'],
      ['cmp_script_params', NULL, ''],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, ''],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, ''],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, []],
    ]);

    $this->formState->expects($this->atLeastOnce())
      ->method('setErrorByName')
      ->with('cmp_script', $this->anything());

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that a correctly formatted GA Measurement ID passes validation.
   *
   * IDs beginning with "G-" followed by uppercase letters and numbers
   * (e.g. "G-ABC123") are the expected Google Analytics 4 format and must
   * not trigger any form errors.
   *
   * @covers ::validateForm
   */
  public function testValidGaMeasurementIdPasses(): void {
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'valid-script'],
      ['cmp_script_params', NULL, ''],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, ''],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, 'G-ABC123'],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, []],
    ]);

    $this->formState->expects($this->never())->method('setErrorByName');

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that a legacy Universal Analytics ID fails validation.
   *
   * IDs in the old "UA-XXXXX" format are not accepted. Only GA4 "G-" prefixed
   * IDs are valid. This must set a form error on the "cmp_ga_measurement_id"
   * field.
   *
   * @covers ::validateForm
   */
  public function testInvalidGaMeasurementIdFails(): void {
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'valid-script'],
      ['cmp_script_params', NULL, ''],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, ''],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, 'UA-12345'],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, []],
    ]);

    $this->formState->expects($this->atLeastOnce())
      ->method('setErrorByName')
      ->with('cmp_ga_measurement_id', $this->anything());

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that an unrecognized CMP version string fails validation.
   *
   * Only "advanced" and "pro" are accepted values for cmp_version. Any other
   * value (e.g. "unknown") must set a form error on the "cmp_version" field.
   *
   * @covers ::validateForm
   */
  public function testInvalidCmpVersionFails(): void {
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'unknown'],
      ['cmp_script', NULL, 'valid-script'],
      ['cmp_script_params', NULL, ''],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, ''],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, ''],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, []],
    ]);

    $this->formState->expects($this->atLeastOnce())
      ->method('setErrorByName')
      ->with('cmp_version', $this->anything());

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that CMP script parameters containing HTML characters fail.
   *
   * Parameters containing angle brackets such as "foo=<script>" must set a
   * form error on the "cmp_script_params" field to prevent XSS injection.
   *
   * @covers ::validateForm
   */
  public function testScriptParamsWithXssCharsFails(): void {
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'valid-script'],
      ['cmp_script_params', NULL, 'foo=<script>alert(1)</script>'],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, ''],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, ''],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, []],
    ]);

    $this->formState->expects($this->atLeastOnce())
      ->method('setErrorByName')
      ->with('cmp_script_params', $this->anything());

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that valid CMP script parameters pass validation.
   *
   * A non-empty parameter string such as "foo=bar&baz=1" must not trigger
   * any form errors when it contains no angle-bracket characters.
   *
   * @covers ::validateForm
   */
  public function testValidScriptParamsPasses(): void {
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'valid-script'],
      ['cmp_script_params', NULL, 'foo=bar&baz=1'],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, ''],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, ''],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, []],
    ]);

    $this->formState->expects($this->never())->method('setErrorByName');

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that an unrecognised consent config value fails validation.
   *
   * Only "notice_behavior" and "consent_model" are valid. Anything else
   * must set a form error on the "cmp_consent_config" field.
   *
   * @covers ::validateForm
   */
  public function testInvalidConsentConfigFails(): void {
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'valid-script'],
      ['cmp_script_params', NULL, ''],
      ['cmp_consent_config', NULL, 'invalid_value'],
      ['cmp_banner', NULL, ''],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, ''],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, []],
    ]);

    $this->formState->expects($this->atLeastOnce())
      ->method('setErrorByName')
      ->with('cmp_consent_config', $this->anything());

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that a banner container ID starting with a digit fails validation.
   *
   * HTML element IDs must begin with a letter per the spec. An ID such as
   * "1invalid" must set a form error on the "cmp_banner" field.
   *
   * @covers ::validateForm
   */
  public function testInvalidBannerIdFails(): void {
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'valid-script'],
      ['cmp_script_params', NULL, ''],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, '1invalid-id'],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, ''],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, []],
    ]);

    $this->formState->expects($this->atLeastOnce())
      ->method('setErrorByName')
      ->with('cmp_banner', $this->anything());

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that a CSS selector containing an HTML tag fails validation.
   *
   * Selectors such as "<script>alert(1)</script>" must set a form error on
   * the "cmp_preferences_selector" field to prevent HTML injection.
   *
   * @covers ::validateForm
   */
  public function testPreferencesSelectorWithHtmlFails(): void {
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'valid-script'],
      ['cmp_script_params', NULL, ''],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, ''],
      ['cmp_preferences_selector', NULL, '<script>alert(1)</script>'],
      ['cmp_ga_measurement_id', NULL, ''],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, []],
    ]);

    $this->formState->expects($this->atLeastOnce())
      ->method('setErrorByName')
      ->with('cmp_preferences_selector', $this->anything());

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that an implied location string with special characters fails.
   *
   * The field accepts only letters, numbers, underscores, hyphens, commas,
   * and spaces. A value such as "opt-out!" containing "!" must set a form
   * error on the "cmp_ga_implied_location" field.
   *
   * @covers ::validateForm
   */
  public function testInvalidImpliedLocationFails(): void {
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'valid-script'],
      ['cmp_script_params', NULL, ''],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, ''],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, ''],
      ['cmp_ga_implied_location', NULL, 'opt-out!'],
      ['cmp_consent_type_mapping', NULL, []],
    ]);

    $this->formState->expects($this->atLeastOnce())
      ->method('setErrorByName')
      ->with('cmp_ga_implied_location', $this->anything());

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that a negative consent type mapping category ID fails validation.
   *
   * TrustArc category IDs must be non-negative integers. A value of "-1"
   * must set a form error on the corresponding mapping field.
   *
   * @covers ::validateForm
   */
  public function testNegativeConsentTypeMappingIdFails(): void {
    $mapping = ['analytics_storage' => ['trustarc_category_id' => '-1']];
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'valid-script'],
      ['cmp_script_params', NULL, ''],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, ''],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, ''],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, $mapping],
    ]);

    $this->formState->expects($this->atLeastOnce())
      ->method('setErrorByName')
      ->with($this->stringContains('trustarc_category_id'), $this->anything());

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

  /**
   * Tests that a non-numeric consent type mapping category ID fails.
   *
   * TrustArc category IDs must be numeric. A non-numeric string such as
   * "abc" must set a form error on the corresponding mapping field.
   *
   * @covers ::validateForm
   */
  public function testNonNumericConsentTypeMappingIdFails(): void {
    $mapping = ['analytics_storage' => ['trustarc_category_id' => 'abc']];
    $this->formState->method('getValue')->willReturnMap([
      ['cmp_version', NULL, 'advanced'],
      ['cmp_script', NULL, 'valid-script'],
      ['cmp_script_params', NULL, ''],
      ['cmp_consent_config', NULL, 'notice_behavior'],
      ['cmp_banner', NULL, ''],
      ['cmp_preferences_selector', NULL, ''],
      ['cmp_ga_measurement_id', NULL, ''],
      ['cmp_ga_implied_location', NULL, ''],
      ['cmp_consent_type_mapping', NULL, $mapping],
    ]);

    $this->formState->expects($this->atLeastOnce())
      ->method('setErrorByName')
      ->with($this->stringContains('trustarc_category_id'), $this->anything());

    $form = [];
    $this->form->validateForm($form, $this->formState);
  }

}
