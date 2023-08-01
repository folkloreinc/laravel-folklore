<?php

namespace Folklore\Support\Concerns;

use Illuminate\Support\Arr;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

trait MakesRequests
{
    protected $requestClient;

    protected function requestJson($url, $method = 'GET', $params = [], $opts = [])
    {
        $headers = array_merge(
            [
                'Accept' => 'application/json',
            ],
            $method == 'POST' || $method == 'PUT'
                ? [
                    'Content-type' => 'application/json',
                ]
                : [],
            data_get($opts, 'headers', [])
        );

        $response = $this->makeRequest(
            $url,
            $method,
            $params,
            array_merge(Arr::except($opts, ['return_errors']), [
                'headers' => $headers,
            ])
        );
        $isSuccess =
            !is_null($response) &&
            $response->getStatusCode() >= 200 &&
            $response->getStatusCode() < 300;

        $returnErrors = data_get($opts, 'return_errors', false);

        return !is_null($response) && ($returnErrors || $isSuccess)
            ? json_decode((string) $response->getBody(), true)
            : null;
    }

    protected function requestWebpage(
        $url,
        $userAgent = 'Mozilla/5.0 (Windows NT 6.0) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.97 Safari/537.11',
        $opts = []
    ) {
        $headers = array_merge(
            [
                'User-Agent' => $userAgent,
            ],
            data_get($opts, 'headers', [])
        );
        $response = $this->makeRequest(
            $url,
            'GET',
            [],
            array_merge(Arr::except($opts, ['return_errors']), [
                'headers' => $headers,
            ])
        );
        $isSuccess =
            !is_null($response) &&
            $response->getStatusCode() >= 200 &&
            $response->getStatusCode() < 300;

        $returnErrors = data_get($opts, 'return_errors', false);

        return !is_null($response) && ($returnErrors || $isSuccess)
            ? (string) $response->getBody()
            : null;
    }

    protected function requestData($url, $method = 'GET', $params = [], $opts = [])
    {
        $response = $this->makeRequest(
            $url,
            $method,
            $params,
            Arr::except($opts, ['return_errors'])
        );
        $isSuccess =
            !is_null($response) &&
            $response->getStatusCode() >= 200 &&
            $response->getStatusCode() < 300;

        $returnErrors = data_get($opts, 'return_errors', false);
        return !is_null($response) && ($returnErrors || $isSuccess)
            ? (string) $response->getBody()
            : null;
    }

    protected function makeRequest($url, $method, $params = [], $opts = [])
    {
        $authorizationHeader = method_exists($this, 'getAuthorizationHeader')
            ? $this->getAuthorizationHeader($url, $method, $params, $opts)
            : null;
        $headers = array_merge(
            !empty($authorizationHeader)
                ? [
                    'Authorization' => $authorizationHeader,
                ]
                : [],
            data_get($opts, 'headers', [])
        );
        $options = Arr::except($opts, ['headers']);
        $contentType = data_get(
            $headers,
            'Content-type',
            data_get($headers, 'Content-Type', data_get($headers, 'content-type'))
        );
        $postKey = $contentType === 'application/json' ? 'json' : 'form_params';

        try {
            $response = $this->getRequestClient()->request(
                $method,
                $url,
                array_merge(
                    [
                        $postKey => $method == 'POST' || $method == 'PUT' ? $params : null,
                        'query' => $method === 'GET' ? $params : null,
                        'headers' => $headers,
                    ],
                    $options
                )
            );
            return $response;
        } catch (RequestException $e) {
            return $e->getResponse();
        } catch (Exception $e) {
            Log::error($e);
            return null;
        }
    }

    protected function getRequestClient()
    {
        if (!$this->requestClient) {
            $opts = [];
            if (method_exists($this, 'getRequestBaseUri')) {
                $opts['base_uri'] = $this->getRequestBaseUri();
            }
            $this->requestClient = new HttpClient($opts);
        }
        return $this->requestClient;
    }
}
