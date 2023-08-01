<?php

namespace Folklore\Contracts\Services\PubSubHubbub;

interface Client
{
    public function subscribe($callback, $topic): bool;

    public function unsubscribe($callback, $topic): bool;
}
