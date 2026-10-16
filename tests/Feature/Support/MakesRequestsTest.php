<?php

namespace Folklore\Tests\Feature\Support;

use Exception;
use Folklore\Support\Concerns\MakesRequests;
use Folklore\Tests\TestCase;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Laravel 12 and 13 turn Guzzle exceptions into their own, but Laravel 11 lets
 * a Guzzle `RequestException` reach `MakesRequests`. These tests throw it from
 * the request client directly.
 */
class MakesRequestsTest extends TestCase
{
    public function test_it_returns_the_response_of_a_request_exception()
    {
        $client = $this->makeClient(new TooManyRedirectsException(
            'Will not follow more than 5 redirects',
            new Request('GET', 'https://example.com/api'),
            new Response(302, ['Content-Type' => 'application/json'], '{"redirected":true}')
        ));

        $this->assertNull($client->json());
        $this->assertSame(['redirected' => true], $client->json(['return_errors' => true]));
        $this->assertSame('{"redirected":true}', $client->data(['return_errors' => true]));
    }

    public function test_it_returns_null_for_a_request_exception_without_a_response()
    {
        $client = $this->makeClient(new RequestException(
            'The request body could not be read',
            new Request('GET', 'https://example.com/api')
        ));

        $this->assertNull($client->json(['return_errors' => true]));
        $this->assertNull($client->data(['return_errors' => true]));
    }

    protected function makeClient(Exception $exception)
    {
        $requestClient = new class($exception)
        {
            public function __construct(protected Exception $exception) {}

            public function withHeaders(array $headers)
            {
                return $this;
            }

            public function withOptions(array $options)
            {
                return $this;
            }

            public function get(string $url, $params = [])
            {
                throw $this->exception;
            }
        };

        return new class($requestClient)
        {
            use MakesRequests;

            public function __construct(protected $fakeRequestClient) {}

            public function json(array $opts = [])
            {
                return $this->requestJson('https://example.com/api', 'GET', [], $opts);
            }

            public function data(array $opts = [])
            {
                return $this->requestData('https://example.com/api', 'GET', [], $opts);
            }

            protected function getRequestClient()
            {
                return $this->fakeRequestClient;
            }
        };
    }
}
