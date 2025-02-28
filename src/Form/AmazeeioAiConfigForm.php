<?php

namespace Drupal\ai_provider_amazeeio\Form;

use Drupal\ai_provider_litellm\Form\LiteLlmAiConfigForm;
use Drupal\ai_provider_litellm\LiteLLM\LiteLlmAiClient;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Amazee.io AI API access.
 */
class AmazeeioAiConfigForm extends LiteLlmAiConfigForm {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_provider_amazeeio.settings';

  /**
   * The host domain to use as the base for Amazee API requests.
   */
  const HOST = 'https://backend.main.amazeeai.us2.amazee.io';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'amazeeio_ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Update some strings.
    $form['api_key']['#title'] = $this->t('Amazee.io AI API Key');
    $form['advanced']['moderation']['#markup'] = '<p>' . $this->t('Moderation is always on by default for any text based call. You can disable it for each request either via code or by changing manually in ai_provider_amazeeio.settings.yml.') . '</p>';

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Note: this overrides the LiteLlmAiConfigForm implementation to ensure
   *   appropriate error messages and channels are used.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG_NAME);

    // Validate the API key against model listing.
    $key = $form_state->getValue('api_key');
    if (empty($key)) {
      $form_state->setErrorByName('api_key', $this->t('The API Key is required.'));
      return;
    }

    $api_key = $this->keyRepository->getKey($key)->getKeyValue();
    if (!$api_key) {
      $form_state->setErrorByName('api_key', $this->t('The API Key is invalid.'));
      return;
    }

    // Make a call to the API to validate the API key.
    $client = new LiteLlmAiClient($this->client, $this->keyRepository, $config->get('host') ?? '', $key, $config->get('moderation') ?? TRUE);
    try {
      if (empty($client->models())) {
        $this->logger('ai_provider_amazeeio')->error('Connected to Amazee.io AI API but there were no models in the response.',);
        $form_state->setErrorByName('api_key', $this->t('The API Key is not working.'));
      }
    }
    catch (\Exception $e) {
      $this->logger('ai_provider_amazeeio')->error('Error connecting to Amazee.io AI API: @error', ['@error' => $e->getMessage()]);
      $form_state->setErrorByName('api_key', $this->t('The API Key is not working.'));
    }
  }

}
