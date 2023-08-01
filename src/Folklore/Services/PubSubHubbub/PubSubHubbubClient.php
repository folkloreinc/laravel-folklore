<?php

namespace Folklore\Services\PubSubHubbub;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use Folklore\Contracts\Services\PubSubHubbub\Client as PubSubHubbubClientContract;
use Folklore\Support\Concerns\MakesRequests;
use Illuminate\Support\Facades\Log;

class PubSubHubbubClient implements PubSubHubbubClientContract
{
    use MakesRequests;

    protected $hub;
    protected $secret;
    protected $options;

    public function __construct($hub, $secret = null, $opts = [])
    {
        $this->hub = $hub;
        $this->secret = $secret;
        $this->options = $opts;
    }

    public function subscribe($callback, $topic): bool
    {
        $response = $this->makeRequest(
            $this->hub,
            'POST',
            array_merge($this->options, [
                'hub.mode' => 'subscribe',
                'hub.secret' => $this->secret,
                'hub.callback' => $callback,
                'hub.topic' => $topic,
            ])
        );
        $statusCode = isset($response) ? $response->getStatusCode() : null;
        $success = $statusCode === 202 || $statusCode === 204;
        if (!$success) {
            Log::error('[PubSubHubbubClient ' . $this->hub . '] ' . (string)$response->getBody());
        }
        return $success;
    }

    public function unsubscribe($callback, $topic): bool
    {
        $response = $this->makeRequest(
            $this->hub,
            'POST',
            array_merge($this->options, [
                'hub.mode' => 'unsubscribe',
                'hub.secret' => $this->secret,
                'hub.callback' => $callback,
                'hub.topic' => $topic,
            ])
        );
        $statusCode = isset($response) ? $response->getStatusCode() : null;
        $success = $statusCode === 202 || $statusCode === 204;
        if (!$success) {
            Log::error('[PubSubHubbubClient ' . $this->hub . '] ' . (string)$response->getBody());
        }
        return $success;
    }
}
