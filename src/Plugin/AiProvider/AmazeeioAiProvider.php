<?php

namespace Drupal\ai_provider_amazeeio\Plugin\AiProvider;

use Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\OpenAiBasedProviderClientBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'amazee.ai AI' provider.
 */
#[AiProvider(
  id: 'amazeeio',
  label: new TranslatableMarkup('amazee.ai AI'),
)]
class AmazeeioAiProvider extends OpenAiBasedProviderClientBase {

  /**
   * The AmazeeAI API client.
   *
   * @var \Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient;
   */
  protected AmazeeClient $amazeeClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [];
  }
}
