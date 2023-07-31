<?php

namespace Folklore\Services\PubSubHubbub;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use Folklore\Contracts\Services\PubSubHubbub\Client as PubSubHubbubClientContract;
use Folklore\Support\Concerns\MakesRequests;

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
        return $this->makeRequest($this->hub, 'POST', [
            'hub.mode' => 'subscribe',
            'hub.secret' => $this->secret,
            'hub.callback' => $callback,
            'hub.topic' => $topic
        ]);
    }

    public function unsubscribe($callback, $topic)
    {
        return $this->makeRequest($this->hub, 'POST', [
            'hub.mode' => 'unsubscribe',
            'hub.secret' => $this->secret,
            'hub.callback' => $callback,
            'hub.topic' => $topic
        ]);
    }
}
