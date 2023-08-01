<?php

namespace Folklore\Services\PubSubHubbub;

use GuzzleHttp\Client as HttpClient;
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

    public function subscribe($callback, $topic)
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
        if (
            isset($response) &&
            $response->getStatusCode() == 202 &&
            $response->getStatusCode() == 204
        ) {
            return true;
        }
        return isset($response) ? (string) $response->getBody() : false;
    }

    public function unsubscribe($callback, $topic)
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
        if (
            isset($response) &&
            $response->getStatusCode() == 202 &&
            $response->getStatusCode() == 204
        ) {
            return true;
        }
        return isset($response) ? (string) $response->getBody() : false;
    }
}
