<?php

namespace Drupal\ai_provider_amazeeio\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Redirects users to the Amazee.io AI provider configuration page on login if needed.
 */
class LoginRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new LoginRedirectSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(AccountInterface $current_user, ConfigFactoryInterface $config_factory, RouteMatchInterface $current_route_match, ModuleHandlerInterface $module_handler) {
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->currentRouteMatch = $current_route_match;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 20];
    return $events;
  }

  /**
   * This method is called whenever the kernel.request event is
   * dispatched.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event.
   */
  public function onKernelRequest(RequestEvent $event) {
    // Only run this on edit profile (which is usually triggered after a one-time login link).
    // We could also add 'entity.user.canonical' to the list.
    // What would be best is if we could run the event for user.login but then only redirect after the login and only for admins.
    if (!in_array($this->currentRouteMatch->getRouteName(), ['entity.user.edit_form'])) {
      return;
    }

    // Only run this if config ai_provider_amazeeio.settings.redirect_on_login is TRUE.
    $config = $this->configFactory->get('ai_provider_amazeeio.settings');
    if (!$config->get('redirect_on_login')) {
      return;
    }

    if ($this->moduleHandler->moduleExists('ai_provider_amazeeio') && $this->currentUser->isAuthenticated() && ($this->currentUser->hasPermission('administer ai providers') || $this->currentUser->hasRole('administrator'))) {
      // Call ai_provider_amazeeio.api_client->getPrivateApiKeys() and log the result.
      $apiClient = \Drupal::service('ai_provider_amazeeio.api_client');
      $apiKey = $apiClient->getPrivateApiKey($config->get('api_key'));

      if (is_null($apiKey)) {
        if ($this->currentRouteMatch->getRouteName() !== 'ai_provider_amazeeio.settings_form' && $this->currentRouteMatch->getRouteName() !== 'user.logout') {
          $url = Url::fromRoute('ai_provider_amazeeio.settings_form')->toString();
          $event->setResponse(new RedirectResponse($url));
        }
      }
    }
  }

}