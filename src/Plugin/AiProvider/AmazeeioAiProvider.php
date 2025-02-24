<?php

namespace Drupal\ai_provider_amazeeio\Plugin\AiProvider;

use Drupal\ai_provider_openai\Plugin\AiProvider\OpenAiProvider;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the 'Amazee.io AI' provider.
 */
#[AiProvider(
  id: 'amazeeio',
  label: new TranslatableMarkup('Amazee.io AI'),
)]
class AmazeeioAiProvider extends OpenAiProvider {
  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai_provider_amazeeio.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    // Load the configuration.
    $definition = Yaml::parseFile($this->moduleHandler->getModule('ai_provider_amazeeio')->getPath() . '/definitions/api_defaults.yml');
    return $definition;
  }

}
