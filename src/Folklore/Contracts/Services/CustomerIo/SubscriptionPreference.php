<?php

namespace Folklore\Contracts\Services\CustomerIo;

interface SubscriptionPreference
{
    public function topic(): string;

    public function subscribed(): bool;
}
