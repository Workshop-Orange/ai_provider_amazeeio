<?php

namespace Drupal\ai_provider_amazeeio\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * A route subscriber to remove settings forms for providers this builds upon.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('ai_provider_openai.settings_form')) {
      $route->setRequirement('_access', 'FALSE');
    }
    if ($route = $collection->get('ai_provider_litellm.settings_form')) {
      $route->setRequirement('_access', 'FALSE');
    }
  }

}
