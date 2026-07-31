<?php

namespace Drupal\ai_provider_amazeeio\AmazeeIoApi;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Stamps the amazee.ai client identification header onto every request.
 *
 * The OpenAI SDK builds its own PSR-7 requests, so the only place to add a
 * header to inference traffic is the PSR-18 client handed to it.
 */
class IdentifiedHttpClient implements ClientInterface {

  public function __construct(
    protected readonly ClientInterface $inner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function sendRequest(RequestInterface $request): ResponseInterface {
    return $this->inner->sendRequest(
      $request->withHeader(AmazeeClient::CLIENT_HEADER, AmazeeClient::clientHeaderValue()),
    );
  }

}
