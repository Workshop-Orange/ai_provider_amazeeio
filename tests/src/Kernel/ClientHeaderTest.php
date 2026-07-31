<?php

namespace Drupal\Tests\ai_provider_amazeeio\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient;
use Drupal\ai_provider_amazeeio\AmazeeIoApi\IdentifiedHttpClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests the X-Amazee-Client request tracking header.
 */
class ClientHeaderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai', 'key', 'ai_provider_amazeeio'];

  /**
   * The header value identifies the module and its version.
   */
  public function testClientHeaderValue(): void {
    $this->assertStringStartsWith('ai_provider_amazeeio/', AmazeeClient::clientHeaderValue());
  }

  /**
   * The PSR-18 wrapper stamps the header onto requests it forwards.
   */
  public function testIdentifiedHttpClientStampsHeader(): void {
    $inner = new class() implements ClientInterface {

      /**
       * The last request forwarded to this client.
       */
      public ?RequestInterface $seen = NULL;

      /**
       * {@inheritdoc}
       */
      public function sendRequest(RequestInterface $request): ResponseInterface {
        $this->seen = $request;
        return new Response(200);
      }

    };

    (new IdentifiedHttpClient($inner))
      ->sendRequest(new Request('POST', 'https://amazeeio.llm/ch1/chat/completions'));

    $this->assertSame(
      AmazeeClient::clientHeaderValue(),
      $inner->seen->getHeaderLine(AmazeeClient::CLIENT_HEADER),
    );
  }

}
