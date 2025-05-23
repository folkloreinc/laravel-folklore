<?php

namespace Folklore\Support\Concerns;

use Illuminate\Support\Arr;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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
        $isSuccess = !is_null($response) && $response->successful();

        $returnErrors = data_get($opts, 'return_errors', false);

        return !is_null($response) && ($returnErrors || $isSuccess) ? $response->json() : null;
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
        return $this->requestData(
            $url,
            'GET',
            [],
            array_merge(Arr::except($opts, ['return_errors']), [
                'headers' => $headers,
            ])
        );
    }

    protected function requestData($url, $method = 'GET', $params = [], $opts = [])
    {
        $response = $this->makeRequest(
            $url,
            $method,
            $params,
            Arr::except($opts, ['return_errors'])
        );
        $isSuccess = !is_null($response) && $response->successful();

        $defaultReturnErrors = method_exists($this, 'getRequestReturnErrors')
            ? $this->getRequestReturnErrors()
            : false;
        $returnErrors = data_get($opts, 'return_errors', $defaultReturnErrors);
        return !is_null($response) && ($returnErrors || $isSuccess) ? $response->body() : null;
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

        $params = method_exists($this, 'getRequestParams')
            ? $this->getRequestParams($url, $method, $params, $opts)
            : $params;

        try {
            $response = $this->getRequestClient()
                ->withHeaders($headers)
                ->withOptions($options)
                ->{strtolower($method)}($url, $params);
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
        $opts = [];
        if (method_exists($this, 'getRequestBaseUri')) {
            $opts['base_uri'] = $this->getRequestBaseUri();
        }

        $client = Http::withOptions($opts);

        $timeout = method_exists($this, 'getRequestTimeout') ? $this->getRequestTimeout() : null;
        if (isset($timeout)) {
            $client = $client->timeout($timeout);
        }

        return $client;
    }

    protected function getRequestTimeout(): ?int
    {
        return null;
    }
}
