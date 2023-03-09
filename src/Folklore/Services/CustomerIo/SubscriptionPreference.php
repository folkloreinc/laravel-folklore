<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\SubscriptionPreference as SubscriptionPreferenceContract;

class SubscriptionPreference implements SubscriptionPreferenceContract
{
    protected $topic;

    protected $subscribed;

    public function __construct(string $topic, bool $subscribed)
    {
        $this->topic = $topic;
        $this->subscribed = $subscribed;
    }

    public function topic(): string
    {
        return $this->topic;
    }

    public function subscribed(): bool
    {
        return $this->subscribed;
    }
}
