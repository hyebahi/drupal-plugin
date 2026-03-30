<?php

namespace Drupal\trustarc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class TrustarcSettingsForm.
 *
 * Provides a settings form for TrustArc module.
 */
class TrustarcSettingsForm extends ConfigFormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a TrustarcSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger, MessengerInterface $messenger) {
    $this->configFactory = $config_factory;
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory')->get('trustarc'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'trustarc_settings_form';
  }

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   Editable config names.
   */
  protected function getEditableConfigNames(): array {
    return ['trustarc.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->configFactory->get('trustarc.settings');

    // CMP Version.
    $form['cmp_version'] = [
      '#type' => 'radios',
      '#title' => $this->t('CMP Version'),
      '#options' => [
        'advanced' => $this->t('Advanced'),
        'pro' => $this->t('Pro'),
      ],
      '#default_value' => $config->get('cmp_version') ?: 'advanced',
    ];

    // CMP Script ID.
    $form['cmp_script'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CMP Script ID'),
    // Tooltip text as description.
      '#description' => $this->t('Your unique TrustArc CMP ID.'),
      '#default_value' => $config->get('cmp_script'),
      '#required' => TRUE,
    ];

    // CMP Script Params.
    $form['cmp_script_params'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CMP Script Params'),
      '#description' => $this->t('Additional parameters for customizing the CMP script.'),
      '#default_value' => $config->get('cmp_script_params'),
      '#required' => FALSE,
    ];

    // Banner Container.
    $form['cmp_banner'] = [
    // Use textarea for flexibility.
      '#type' => 'textfield',
      '#title' => $this->t('Banner Container'),
      '#description' => $this->t('The ID of the HTML element where the CMP banner will be displayed.'),
      '#default_value' => $config->get('cmp_banner') ?: 'consent_blackbar',
    ];

    // Example for Checkbox (cmp_preferences):
    $form['cmp_preferences'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Cookie Preferences Link'),
      '#description' => $this->t('Enable to display a link that opens the cookie preferences modal.'),
      '#default_value' => $config->get('cmp_preferences'),
    ];

    // Query Selector for Preferences Link.
    $form['cmp_preferences_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Query Selector'),
      '#description' => $this->t('A CSS selector to specify where the cookie preferences link should be placed. For example, "#footer-links" will place the link inside the element with the ID "footer-links". If left blank, the link will be appended to the &lt;body&gt; tag.'),
      '#default_value' => $config->get('cmp_preferences_selector'),
      '#states' => [
        'visible' => [
          ':input[name="cmp_preferences"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Fire Custom Events for GTM.
    $form['cmp_standard_event_listener'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fire Custom Events for GTM'),
      '#description' => $this->t('Enable this option to fire custom events according to the categories in the CMP. This allows for more granular tracking and event handling based on user consent choices. This will also push events to the dataLayer for further use in your GTM. For example, "GDPR Pref Allows 1", "GDPR Pref Allows 2", etc.'),
      '#default_value' => $config->get('cmp_standard_event_listener'),
    ];

    // Consent Behavior.
    $form['cmp_consent_config'] = [
      '#type' => 'select',
      '#title' => $this->t('Consent Behavior'),
      '#description' => $this->t('Choose which TrustArc consent signal to use. "Notice Behavior" uses the notice_behavior cookie, while "Consent Model" uses TrustArc JavaScript consent model.'),
      '#options' => [
        'notice_behavior' => $this->t('Notice Behavior (Cookie)'),
        'consent_model' => $this->t('Consent Model (JavaScript)'),
      ],
      '#default_value' => $config->get('cmp_consent_config') ?: 'notice_behavior',
    ];

    // Opt-out setting by location.
    $form['cmp_ga_implied_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opt-out Setting'),
      '#description' => $this->t('Specify regions that should be treated as opt-out, separated by commas (for example: us, eu). Use "none" for unprovisioned countries. When Consent Behavior is set to Consent Model and this is empty, it defaults to "opt-out".'),
      '#default_value' => $config->get('cmp_ga_implied_location'),
    ];

    // Enable Google Consent Mode.
    $form['cmp_google_consent_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Google Consent Mode'),
      '#description' => $this->t('Enable Google Consent Mode v2 to dynamically adjust the behavior of Google tags (like Google Analytics and Google Ads) based on the user"s consent choices.'),
      '#default_value' => $config->get('cmp_google_consent_mode'),
    ];

    // GA Measurement ID (Conditional)
    $form['cmp_ga_measurement_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GA Measurement ID'),
      '#description' => $this->t('Your Google Analytics Measurement ID (e.g., "G-XXXXXXXXXX"). This is required to use Google Consent Mode with Google Analytics.'),
      '#default_value' => $config->get('cmp_ga_measurement_id'),
      '#states' => [
        'visible' => [
          ':input[name="cmp_google_consent_mode"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Ads Data Redaction (Conditional)
    $form['cmp_ads_data_redaction'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Data Redaction'),
      '#description' => $this->t('When enabled, Google Analytics will redact user data when consent for analytics is not granted. This helps protect user privacy.'),
      '#default_value' => $config->get('cmp_ads_data_redaction'),
      '#states' => [
        'visible' => [
          ':input[name="cmp_google_consent_mode"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Enable URL Passthrough (Conditional)
    $form['cmp_url_passthrough'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable URL Passthrough'),
      '#description' => $this->t('When enabled, URL parameters will be passed through to Google Analytics even when consent is not granted. This can be useful for campaign tracking.'),
      '#default_value' => $config->get('cmp_url_passthrough'),
      '#states' => [
        'visible' => [
          ':input[name="cmp_google_consent_mode"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['google_consent_mode_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Google Consent Mode Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    // Consent Type Mapping (Table) - STATIC.
    $form['google_consent_mode_settings']['cmp_consent_type_mapping'] = [
      '#type' => 'table',
      '#title' => $this->t('Consent Type Mapping'),
      '#description' => $this->t('Map TrustArc consent categories to Google Consent Mode v2 consent types.'),
      '#header' => [
        $this->t('Google Consent Type'),
        $this->t('TrustArc Category ID'),
      ],
      '#rows' => [],
      '#table_id' => 'consent-type-mapping-table',
      '#prefix' => '<div id="consent-type-mapping-container">',
      '#suffix' => '</div>',
      // Correctly added #states.
      '#states' => [
        'visible' => [
          ':input[name="cmp_google_consent_mode"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Google Consent Mode v2 consent types.
    $consent_types = [
      'ad_storage' => 'Ad Storage',
      'ad_user_data' => 'Ad User Data',
      'ad_personalization' => 'Ad Personalization',
      'personalization_storage' => 'Personalization Storage',
      'analytics_storage' => 'Analytics Storage',
      'functionality_storage' => 'Functionality Storage',
      'security_storage' => 'Security Storage',
      'wait_for_update' => 'Wait for Update',
    ];

    foreach ($consent_types as $key => $label) {
      $form['google_consent_mode_settings']['cmp_consent_type_mapping'][$key] = [
        'google_consent_type' => [
          '#markup' => $label,
        ],
        'trustarc_category_id' => [
          '#type' => 'number',
          '#attributes' => ['class' => ['trustarc-category-id']],
          '#default_value' => $key === 'wait_for_update'
            ? ($config->get("cmp_consent_type_mapping.$key.trustarc_category_id") ?? 500)
            : ($config->get("cmp_consent_type_mapping.$key.trustarc_category_id") ?? ''),
        ],
        '#states' => [
          'required' => [
            ':input[name="cmp_google_consent_mode"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    // Attach the library.
    $form['#attached']['library'][] = 'trustarc/trustarc_admin';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $cmp_version = $form_state->getValue('cmp_version');
    if (!in_array($cmp_version, ['advanced', 'pro'], TRUE)) {
      $form_state->setErrorByName('cmp_version', $this->t('Invalid CMP version selected.'));
    }

    $cmp_script = trim((string) $form_state->getValue('cmp_script'));
    $form_state->setValue('cmp_script', $cmp_script);
    if ($cmp_script === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $cmp_script)) {
      $form_state->setErrorByName('cmp_script', $this->t('CMP Script ID may only contain letters, numbers, dots, underscores, and hyphens.'));
    }

    $cmp_script_params = trim((string) $form_state->getValue('cmp_script_params'));
    $form_state->setValue('cmp_script_params', $cmp_script_params);
    if ($cmp_script_params !== '' && preg_match('/[<>]/', $cmp_script_params)) {
      $form_state->setErrorByName('cmp_script_params', $this->t('CMP Script Params contains invalid characters.'));
    }

    $cmp_consent_config = (string) $form_state->getValue('cmp_consent_config');
    if (!in_array($cmp_consent_config, ['notice_behavior', 'consent_model'], TRUE)) {
      $form_state->setErrorByName('cmp_consent_config', $this->t('Invalid consent behavior selected.'));
    }

    $cmp_banner = trim((string) $form_state->getValue('cmp_banner'));
    $form_state->setValue('cmp_banner', $cmp_banner);
    if ($cmp_banner !== '' && !preg_match('/^[A-Za-z][A-Za-z0-9_\-:.]*$/', $cmp_banner)) {
      $form_state->setErrorByName('cmp_banner', $this->t('Banner Container must be a valid HTML element ID.'));
    }

    $cmp_preferences_selector = trim((string) $form_state->getValue('cmp_preferences_selector'));
    $form_state->setValue('cmp_preferences_selector', $cmp_preferences_selector);
    if ($cmp_preferences_selector !== '' && preg_match('/<[a-zA-Z]|>\\s*[^>]*</i', $cmp_preferences_selector)) {
      $form_state->setErrorByName('cmp_preferences_selector', $this->t('Query Selector cannot contain HTML tags or script content.'));
    }

    $cmp_ga_measurement_id = trim((string) $form_state->getValue('cmp_ga_measurement_id'));
    $form_state->setValue('cmp_ga_measurement_id', $cmp_ga_measurement_id);
    if ($cmp_ga_measurement_id !== '' && !preg_match('/^G-[A-Z0-9]+$/', $cmp_ga_measurement_id)) {
      $form_state->setErrorByName('cmp_ga_measurement_id', $this->t('GA Measurement ID must start with G- and contain only uppercase letters and numbers.'));
    }

    $cmp_ga_implied_location = trim((string) $form_state->getValue('cmp_ga_implied_location'));
    $form_state->setValue('cmp_ga_implied_location', $cmp_ga_implied_location);
    if ($cmp_ga_implied_location !== '' && !preg_match('/^[A-Za-z0-9_,\-\s]+$/', $cmp_ga_implied_location)) {
      $form_state->setErrorByName('cmp_ga_implied_location', $this->t('Implied Location contains invalid characters.'));
    }

    $cmp_consent_type_mapping = $form_state->getValue('cmp_consent_type_mapping') ?? [];
    foreach ($cmp_consent_type_mapping as $consent_type => $mapping) {
      $category_id = $mapping['trustarc_category_id'] ?? '';
      if ($category_id === '') {
        continue;
      }
      if (!is_numeric($category_id) || (int) $category_id < 0) {
        $form_state->setErrorByName("cmp_consent_type_mapping][$consent_type][trustarc_category_id", $this->t('TrustArc Category ID must be a non-negative number.'));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $values = $form_state->getValues();
      // Ensure cmp_preferences_selector is saved even if hidden by form states.
      $preferences_selector = isset($values['cmp_preferences_selector']) ? trim((string) $values['cmp_preferences_selector']) : '';

      $this->configFactory->getEditable('trustarc.settings')
        ->set('cmp_version', $values['cmp_version'])
        ->set('cmp_script', $values['cmp_script'])
        ->set('cmp_script_params', $values['cmp_script_params'])
        ->set('cmp_banner', $values['cmp_banner'])
        ->set('cmp_preferences', $values['cmp_preferences'])
        ->set('cmp_preferences_selector', $preferences_selector)
        ->set('cmp_standard_event_listener', $values['cmp_standard_event_listener'])
        ->set('cmp_consent_config', $values['cmp_consent_config'])
        ->set('cmp_google_consent_mode', $values['cmp_google_consent_mode'])
        ->set('cmp_ga_measurement_id', $values['cmp_ga_measurement_id'])
        ->set('cmp_ads_data_redaction', $values['cmp_ads_data_redaction'])
        ->set('cmp_ga_implied_location', $values['cmp_ga_implied_location'])
        ->set('cmp_url_passthrough', $values['cmp_url_passthrough'])
        ->set('cmp_consent_type_mapping', $values['cmp_consent_type_mapping'])
        ->save();

      parent::submitForm($form, $form_state);
    }
    catch (\Exception $e) {
      // Log the error.
      $this->logger->error('Error submitting TrustArc settings form: @message', ['@message' => $e->getMessage()]);
      $this->messenger->addError($this->t('An error occurred while saving the settings: @message', ['@message' => $e->getMessage()]));
    }
  }

}
