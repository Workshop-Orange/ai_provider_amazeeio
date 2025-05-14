<?php

namespace Drupal\ai_provider_amazeeio\AmazeeIoApi;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Utils;
use Psr\Http\Message\ResponseInterface;

/**
 * Client for Amazee private key API.
 */
class AmazeeClient implements ClientInterface {

  /**
   * The auth token to use for requests.
   *
   * @var string
   */
  protected string $authToken = '';

  /**
   * The host URI to make calls against.
   *
   * @var string
   */
  protected string $host = '';

  /**
   * Construct an AmazeeClient.
   *
   * @param \GuzzleHttp\Client $client
   *   A Guzzle client to use for requests.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   A logger factory.
   */
  public function __construct(
    protected Client $client,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function setToken(string $token): void {
    $this->authToken = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function setHost(string $host): void {
    $this->host = $host;
  }

  /**
   * {@inheritdoc}
   */
  public function login(string $username, string $password): string {
    try {
      $response = $this->makeRequest('POST', '/auth/login', [
        'username' => $username,
        'password' => $password,
      ]);
    }
    catch (ClientException | \Exception $e) {
      $this->loggerFactory->get('ai_provider_amazeeio')->error('Failed to login to Amazee.io: @error', ['@error' => $e->getMessage()]);
      return '';
    }

    $response_body = json_decode($response->getBody()->getContents());
    if (empty($response_body->access_token)) {
      $this->loggerFactory->get('ai_provider_amazeeio')->error('Amazee.io login returned success with empty access token.');
      return '';
    }

    return $response_body->access_token;
  }

  /**
   * {@inheritdoc}
   */
  public function logout(): bool {
    try {
      $this->makeRequest('POST', '/auth/logout');
    }
    catch (ClientException | \Exception $e) {
      $this->loggerFactory->get('ai_provider_amazeeio')->error('Failed to log out of Amazee.io: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Request a validation code for a given email address.
   */
  public function requestCode(string $email): void {
    try {
      $this->makeRequest('POST', '/auth/validate-email', ['email' => $email]);
    }
    catch (ClientException | \Exception $e) {
      $this->loggerFactory->get('ai_provider_amazeeio')->error('Failed to validate email: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Validate an email validation code.
   *
   * @return ?string
   *   The access token for this account or null if the code was invalid.
   */
  public function validateCode(string $email, string $code): ?string {
    try {
      $result = $this->makeRequest('POST', '/auth/sign-in', ['username' => $email, 'verification_code' => $code]);
      $data = Utils::jsonDecode($result->getBody()->getContents(), TRUE);
      return $data['access_token'];
    }
    catch (ClientException | \Exception $e) {
      $this->loggerFactory->get('ai_provider_amazeeio')->error('Failed to validate email: @error', ['@error' => $e->getMessage()]);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function register(string $email, string $password): string {
    try {
      $this->makeRequest('POST', '/auth/register', [
        'email' => $email,
        'password' => $password,
      ]);
    }
    catch (ClientException | \Exception $e) {
      $this->loggerFactory->get('ai_provider_amazeeio')->error('Failed to register with Amazee.io: @error', ['@error' => $e->getMessage()]);
      return '';
    }

    return $this->login($email, $password);
  }

  /**
   * {@inheritdoc}
   */
  public function authorized(): bool {
    try {
      $this->makeRequest('GET', '/auth/me');
      return TRUE;
    }
    catch (ClientException | \Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRegions(): array {
    try {
      $response = $this->makeRequest('GET', '/regions');
    }
    catch (ClientException | \Exception $e) {
      $this->loggerFactory->get('ai_provider_amazeeio')->error('Failed to get current list of regions from Amazee.io: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }

    $regions = [];
    $region_response = json_decode($response->getBody()->getContents());
    if ($region_response) {
      foreach ($region_response as $region) {
        if ($region->is_active) {
          $regions[$region->id] = $region->name;
        }
      }
    }
    return $regions;
  }

  /**
   * {@inheritdoc}
   */
  public function createPrivateAiKey(string $region_id, string $name): array {
    try {
      $response = $this->makeRequest('POST', '/private-ai-keys', [
        'region_id' => $region_id,
        'name' => $name,
      ]);
    }
    catch (ClientException | \Exception $e) {
      $this->loggerFactory->get('ai_provider_amazeeio')->error('Failed to create private key Amazee.io: @error', ['@error' => $e->getMessage()]);
      return [];
    }
    $response = $response->getBody()->getContents();
    $response_body = json_decode($response);
    return [
      'litellm_token' => $response_body->litellm_token,
      'litellm_api_url' => $response_body->litellm_api_url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPrivateApiKeys(): array {
    try {
      $response = $this->makeRequest('GET', '/private-ai-keys');
    }
    catch (ClientException | \Exception $e) {
      $this->loggerFactory->get('ai_provider_amazeeio')->error('Failed to get existing private keys Amazee.io: @error', ['@error' => $e->getMessage()]);
      return [];
    }

    // @todo Create DTO for API key responses.
    $response_body = json_decode($response->getBody()->getContents());

    $keys = [];
    foreach ($response_body as $value) {
      if ($value->litellm_api_url !== 'https://demo.litellm.ai') {
        $keys[] = $value;
      }
    }
    return $keys;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrivateApiKey(string $api_key): ?\stdClass {
    try {
      foreach ($this->getPrivateApiKeys() as $private_api_key) {
        if ($private_api_key->litellm_token === $api_key) {
          return $private_api_key;
        }
      }
    }
    catch (ClientException | \Exception $e) {
      $this->loggerFactory->get('ai_provider_amazeeio')->error('Failed to get existing private key @id from Amazee.io: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }

    $this->loggerFactory->get('ai_provider_amazeeio')->error('Existing private key @id does not exist.', ['@id' => $api_key]);
    return NULL;
  }

  /**
   * Helper method to make requests against the API.
   *
   * Adds standard headers (Content-Type, Authorization).
   *
   * @param string $type
   *   The type of request. GET or POST.
   * @param string $endpoint
   *   The endpoint to call without the host/domain.
   * @param array|null $body
   *   Optional body parameters to send.
   * @param array $headers
   *   Optional additional headers to send.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response from the API.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function makeRequest(string $type, string $endpoint, ?array $body = NULL, array $headers = []): ResponseInterface {
    if (empty($this->host)) {
      throw new \Exception('Missing host');
    }

    // Add any defaults to the headers and body.
    $headers = [
      'Content-Type' => 'application/json',
    ] + $headers;
    if ($this->authToken) {
      $headers['Authorization'] = 'Bearer ' . $this->authToken;
    }

    $body = $body ? json_encode($body) : NULL;

    switch ($type) {
      case 'GET':
        return $this->client->get($this->host . $endpoint, [
          'headers' => $headers,
          'body' => $body,
        ]);

      case 'POST':
        return $this->client->post($this->host . $endpoint, [
          'headers' => $headers,
          'body' => $body,
        ]);

      default:
        throw new \InvalidArgumentException('Only GET and POST request types are supported.');
    }
  }

}
