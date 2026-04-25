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

}
