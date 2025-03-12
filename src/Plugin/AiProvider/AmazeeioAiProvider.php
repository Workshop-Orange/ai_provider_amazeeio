<?php

namespace Drupal\ai_provider_amazeeio\Plugin\AiProvider;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai_provider_litellm\Plugin\AiProvider\LiteLlmAiProvider;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Amazee.io AI' provider.
 */
#[AiProvider(
  id: 'amazeeio',
  label: new TranslatableMarkup('Amazee.io AI'),
)]
class AmazeeioAiProvider extends LiteLlmAiProvider {

  /**
   * The AI Provider Manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->aiProviderManager = $container->get('ai.provider');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai_provider_amazeeio.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function postSetup(): void {
    foreach ($this->getDefaultModels() as $operation_type => $model) {
      $this->aiProviderManager->defaultIfNone($operation_type, $this->getPluginDefinition()['id'], $model);
    }
  }

  /**
   * Get the default models available for the backend.
   *
   * @return array<string, string>
   *   Keys are operation types, values are the models.
   */
  public function getDefaultModels(): array {
    $default_models = [];

    try {
      $this->loadClient();
      $models = array_keys($this->liteLlmClient->models());
      $operation_types = array_merge(
        array_map(
          fn(array $operation_type) => $operation_type['id'],
          $this->aiProviderManager->getOperationTypes(),
        ),
        ['chat_with_complex_json', 'chat_with_image_vision'],
      );
      foreach ($operation_types as $operation_type) {
        if (in_array($operation_type, $models)) {
          $default_models[$operation_type] = $operation_type;
        }
      }
    }
    // Ignore exceptions, as the provider may not be authenticated yet.
    catch (ClientException) {
    }

    return $default_models;
  }

}
