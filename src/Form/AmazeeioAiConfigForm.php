<?php

namespace Drupal\ai_provider_amazeeio\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_provider_amazeeio\AmazeeIoApi\ClientInterface;
use Drupal\ai_provider_litellm\Form\LiteLlmAiConfigForm;
use Drupal\ai_provider_litellm\LiteLLM\LiteLlmAiClient;
use Drupal\ai_provider_openai\OpenAiHelper;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Amazee.io AI API access Form.
 */
class AmazeeioAiConfigForm extends LiteLlmAiConfigForm {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_provider_amazeeio.settings';

  /**
   * Constructs a new LiteLlmAiConfigForm object.
   */
  public function __construct(
    AiProviderPluginManager $aiProviderManager,
    KeyRepositoryInterface $keyRepository,
    OpenAiHelper $openAiHelper,
    Client $client,
    protected ClientInterface $amazeeClient,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($aiProviderManager, $keyRepository, $openAiHelper, $client);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai.provider'),
      $container->get('key.repository'),
      $container->get('ai_provider_openai.helper'),
      $container->get('http_client'),
      $container->get('ai_provider_amazeeio.api_client'),
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'amazeeio_ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::CONFIG_NAME);

    $form['#id'] = Html::getUniqueId('amazee-config-form');
    // Add the custom Amazee section.
    $form['amazee'] = [
      '#type' => 'details',
      '#title' => $this->t('Amazee.io login'),
      '#attributes' => [
        // ID used for AJAX callback wrapper.
        'id' => 'amazee-details',
      ],
      '#open' => TRUE,
    ];

    if ($auth_token = $this->getTempStore()->get('access_token')) {
      $this->amazeeClient->setToken($auth_token);
    }
    $this->amazeeClient->setHost($config->get('amazee_host') ?? '');

    if (!$auth_token) {
      $this->loginRegisterForm($form['amazee'], $form_state, $form['#id']);
    }
    else {
      $authorized = $this->amazeeClient->authorized();
      if (!$authorized) {
        $this->loginRegisterForm($form['amazee'], $form_state, $form['#id']);
        // Clear the token as it no longer works.
        $this->getTempStore()->delete('access_token');
        $this->amazeeClient->setToken('');
      }
      else {
        // We don't need to see the Amazee.io login/register if authorized.
        $form['amazee']['#access'] = FALSE;
      }
    }

    $this->apiKeysForm($form, $form_state, $authorized ?? FALSE);

    // Show key usage details.
    $this->keyUsageForm(
      $form,
      $form_state,
      $config->get('host') ?? '',
      $config->get('api_key') ?? ''
    );

    return $form;
  }

  /**
   * Add Login/Register subform to the form.
   *
   * @param array $sub_form
   *   An array of form elements to add the subform to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $form_id
   *   The Form ID.
   */
  protected function loginRegisterForm(array &$sub_form, FormStateInterface $form_state, string $form_id): void {
    // Integrate with the Amazee AI Keys as a Service API.
    $sub_form['login'] = [
      '#type' => 'details',
      '#title' => $this->t('Login'),
      'login_username' => [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
      ],
      'login_password' => [
        '#type' => 'password',
        '#title' => $this->t('Password'),
      ],
      'login_button' => [
        '#type' => 'submit',
        '#value' => $this->t('Login'),
        '#validate' => ['::loginValidate'],
        '#submit' => ['::loginSubmit'],
        '#ajax' => [
          'callback' => '::wholeFormAjax',
          'wrapper' => $form_id,
        ],
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
    ];

    $sub_form['register'] = [
      '#type' => 'details',
      '#title' => $this->t('Register'),
      'register_username' => [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
      ],
      'register_password' => [
        '#type' => 'password',
        '#title' => $this->t('Password'),
      ],
      'register_button' => [
        '#type' => 'button',
        '#value' => $this->t('Register'),
        '#validate' => ['::registerValidate'],
        '#submit' => ['::registerSubmit'],
        '#ajax' => [
          'callback' => '::wholeFormAjax',
          'wrapper' => $form_id,
        ],
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
    ];
  }

  /**
   * Add API key usage subform to the form.
   *
   * @param array<string, mixed> $form
   *   An array of form elements to add the subform to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function selectKeyForm(array &$form, FormStateInterface $form_state): void {
    $current_key = $this->config(static::CONFIG_NAME)->get('api_key');
    $key_options = $this->getApiKeys();
    $form['api_key'] = [
      '#type' => 'select',
      '#title' => $this->t('API key'),
      '#description' => $this->t('Select an existing API key to use.'),
      '#options' => $key_options,
      '#default_value' => $this->keyRepository->getKey('amazeeio_ai')?->getKeyValue(),
      '#required' => TRUE,
      '#access' => count($key_options) > 1,
    ];

    $form['api_key_none'] = [
      '#markup' => $this->t('<p>You currently do not have any API keys.</p>'),
      '#access' => count($key_options) === 1,
    ];

    $form['show_generate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate new key'),
      '#validate' => ['::showGenerateValidate'],
      '#submit' => ['::showGenerateSubmit'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::wholeFormAjax',
        'wrapper' => $form['#id'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#access' => count($key_options) !== 1,
    ];
  }

  /**
   * Add key and usage info for the current key to a form.
   *
   * @param array $form
   *   The form to add to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $host
   *   The LiteLLM host domain.
   * @param string $api_key
   *   The LiteLLM API Key.
   */
  protected function keyUsageForm(array &$form, FormStateInterface $form_state, string $host, string $api_key): void {
    if (empty($host) || empty($api_key) || !$this->keyRepository->getKey('amazeeio_ai')->getKeyValue()) {
      // Show nothing if we don't have an API key or host yet.
      return;
    }

    $client = new LiteLlmAiClient($this->client, $this->keyRepository, $host, $api_key);
    $keys = $client->keyInfo();
    $key_info = reset($keys);

    $form['usage'] = [
      '#theme' => 'table',
      '#rows' => [],
      '#weight' => 20,
    ];

    if ($key_info->info->key_alias) {
      $form['usage']['#rows'][] = [
        $this->t('Name'),
        $key_info->info->key_alias,
      ];
    }

    $form['usage']['#rows'][] = [
      $this->t('Key'),
      $key_info->info->key_name,
    ];

    $form['usage']['#rows'][] = [
      $this->t('Spend ($)'),
      number_format($key_info->info->spend, 5),
    ];

    $form['usage']['#rows'][] = [
      $this->t('Max budget ($)'),
      $key_info->info->max_budget === NULL ? $this->t('N/A') : number_format($key_info->info->max_budget, 2),
    ];

    $form['usage']['#rows'][] = [
      $this->t('Blocked'),
      $key_info->info->blocked ? $this->t('Yes') : $this->t('No'),
    ];

    foreach ($form['usage']['#rows'] as &$row) {
      $row[0] = [
        'data' => ['#markup' => $row[0]],
        'header' => TRUE,
      ];
    }
  }

  /**
   * Add API keys section of the form (selecting existing & generating new).
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param bool $authorized
   *   Whether the user is currently authorized with the Amazee API.
   */
  protected function apiKeysForm(array &$form, FormStateInterface $form_state, bool $authorized): void {
    $generate_new = $form_state->get('amazee_show_generate') ?? FALSE;
    if ($generate_new) {
      $this->generateApiKeyForm($form, $form_state);
    }
    elseif ($authorized) {
      $this->selectKeyForm($form, $form_state);
    }
  }

  /**
   * Add generate API key subform to the form.
   *
   * @param array $form
   *   An array of form elements to add the subform to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function generateApiKeyForm(array &$form, FormStateInterface $form_state): void {
    $form['key_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('A name for the key to help identify it.'),
    ];

    try {
      $regions = $this->amazeeClient->getRegions();
    }
    catch (ClientException $e) {
      // Do nothing as form will handle empty regions array.
    }
    $form['region'] = [
      '#type' => 'select',
      '#title' => $this->t('Region'),
      '#options' => $regions ?? [],
      '#access' => !empty($regions),
    ];

    $form['generate_key'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate API key'),
      '#validate' => ['::generateKeyValidate'],
      '#submit' => ['::generateKeySubmit'],
      '#ajax' => [
        'callback' => '::wholeFormAjax',
        'wrapper' => $form['#id'],
      ],
    ];
  }

  /**
   * AJAX handler for Amazee section of the form.
   *
   * @param array<string, mixed> $form
   *   The form being submitted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state being submitted.
   *
   * @return array<string, mixed>
   *   The Amazee-specific section of the form.
   */
  public function amazeeAjax(array $form, FormStateInterface $form_state): array {
    return $form['amazee'];
  }

  /**
   * Validation handler for Login action.
   *
   * @param array<string, mixed> $form
   *   The form being submitted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state being submitted.
   */
  public function loginValidate(array &$form, FormStateInterface $form_state): void {
    $config = $this->config(static::CONFIG_NAME);
    $this->amazeeClient->setHost($config->get('amazee_host') ?? '');
    $access_token = $this->amazeeClient->login($form_state->getValue('login_username'), $form_state->getValue('login_password'));
    if (empty($access_token)) {
      $form_state->setError($form['amazee']['login'], $this->t('Could not log in to Amazee.io. Please check your details.'));
      return;
    }

    $this->getTempStore()->set('access_token', $access_token);
  }

  /**
   * Submit handler for Login action.
   *
   * @param array<string, mixed> $form
   *   The form being submitted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state being submitted.
   */
  public function loginSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

  /**
   * Validation handler for Register action.
   *
   * @param array<string, mixed> $form
   *   The form being submitted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state being submitted.
   */
  public function registerValidate(array &$form, FormStateInterface $form_state): void {
    $access_token = $this->amazeeClient->register($form_state->getValue('register_username'), $form_state->getValue('register_password'));
    if (empty($access_token)) {
      $form_state->setError($form['amazee']['register'], $this->t('Could not register with Amazee.io.'));
      return;
    }

    $this->getTempStore()->set('access_token', $access_token);
  }

  /**
   * Submit handler for Register action.
   *
   * @param array<string, mixed> $form
   *   The form being submitted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state being submitted.
   */
  public function registerSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

  /**
   * Validation handler for show generate key form.
   *
   * @param array<string, mixed> $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function showGenerateValidate(array &$form, FormStateInterface $form_state): void {
    // Intentionally left blank.
  }

  /**
   * Submit handler for showing the generate key form.
   *
   * @param array<string, mixed> $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function showGenerateSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
    $form_state->set('amazee_show_generate', TRUE);
  }

  /**
   * AJAX callback to replace the whole form.
   *
   * @param array<string, mixed> $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array<string, mixed>
   *   The whole form.
   */
  public function wholeFormAjax(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * Validation handler for Generate Key action.
   *
   * @param array<string, mixed> $form
   *   The form being submitted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state being submitted.
   */
  public function generateKeyValidate(array &$form, FormStateInterface $form_state): void {
    $region = $form_state->getValue('region');
    if (empty($region)) {
      $form_state->setError($form['amazee']['region'], $this->t('Unable to fetch list of regions from Amazee.io.'));
      return;
    }

    $key_name = $form_state->getValue('key_name');
    $access_token = $this->getTempStore()->get('access_token');
    if (empty($access_token)) {
      $form_state->setError($form['amazee'], $this->t('You have been logged out. Please log in and try again.'));
      return;
    }
    $config = $this->config(static::CONFIG_NAME);

    $this->amazeeClient->setToken($access_token);
    $this->amazeeClient->setHost($config->get('amazee_host') ?? '');

    $private_key = $this->amazeeClient->createPrivateAiKey($region, $key_name);
    // If successful remove the generate form.
    if ($private_key['litellm_token'] && $private_key['litellm_api_url']) {
      $form_state->set('amazee_show_generate', FALSE);
    }
    $form_state->setRebuild();
  }

  /**
   * Submit handler for Generate Key action.
   *
   * @param array<string, mixed> $form
   *   The form being submitted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state being submitted.
   */
  public function generateKeySubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Override parent with intentionally left blank as the flow for this form
    // validates API key selection elsewhere.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config(static::CONFIG_NAME);
    $this->amazeeClient->setToken($this->getTempStore()->get('access_token') ?? '');
    $this->amazeeClient->setHost($config->get('amazee_host') ?? '');
    $api_key = $this->amazeeClient->getPrivateApiKey($form_state->getValue('api_key'));
    if ($api_key) {
      // Set the provider config, using a know key name to ease support
      // preconfigured environments.
      $this->config(static::CONFIG_NAME)
        ->set('host', $api_key->litellm_api_url)
        ->set('api_key', 'amazeeio_ai')
        ->save();

      // Load or create the Amazee.io key.
      $key_storage = $this->entityTypeManager->getStorage('key');
      $key = $key_storage->load('amazeeio_ai') ??
        $key_storage->create([
          'id' => 'amazeeio_ai',
          'label' => 'Amazee.io AI API Key',
          'description' => 'Automatically created by the Amazee.io AI provider.',
        ]);
      // Update the key config.
      $key
        ->set('key_provider', 'config')
        ->set('key_provider_settings', ['key_value' => $api_key->litellm_token])
        ->set('key_input', 'text_field')
        ->save();

      // Set the default models where available.
      $litellm_client = new LiteLlmAiClient($this->client, $this->keyRepository, $api_key->litellm_api_url, $api_key->litellm_token);
      $models = array_keys($litellm_client->models());
      $operation_types = array_merge(
        array_map(
          fn (array $operation_type) => $operation_type['id'],
          $this->aiProviderManager->getOperationTypes(),
        ),
        ['chat_with_complex_json', 'chat_with_image_vision'],
      );
      foreach ($operation_types as $operation_type) {
        if (in_array($operation_type, $models)) {
          $this->aiProviderManager->defaultIfNone($operation_type, 'amazeeio', $operation_type);
        }
      }
    }
  }

  /**
   * Get the temp store.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   *   The temp store.
   */
  protected function getTempStore(): PrivateTempStore {
    return $this->tempStoreFactory->get('amazeeio_ai');
  }

  /**
   * Get the available API keys suitable for a select element.
   *
   * @return array<string, string>
   *   The API keys, with the token as the key and the name as the value.
   *   Includes an empty option.
   */
  protected function getApiKeys(): array {
    $keys = [
      '' => $this->t('- Select -'),
    ];
    foreach ($this->amazeeClient->getPrivateApiKeys() as $private_api_key) {
      $keys[$private_api_key->litellm_token] = new FormattableMarkup(
        '@name [@region]',
        [
          '@name' => $private_api_key->name ?? substr_replace($private_api_key->litellm_token, '...', 3, -4),
          '@region' => $private_api_key->region,
        ],
      );
    }

    return $keys;
  }

}
