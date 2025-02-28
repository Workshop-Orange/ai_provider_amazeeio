<?php

namespace Drupal\ai_provider_amazeeio\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai_provider_litellm\Plugin\AiProvider\LiteLlmAiProvider;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'Amazee.io AI' provider.
 */
#[AiProvider(
  id: 'amazeeio',
  label: new TranslatableMarkup('Amazee.io AI'),
)]
class AmazeeioAiProvider extends LiteLlmAiProvider {

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai_provider_amazeeio.settings');
  }

}
