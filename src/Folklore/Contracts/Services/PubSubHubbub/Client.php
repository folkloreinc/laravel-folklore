<?php

namespace Folklore\Contracts\Services\PubSubHubbub;

interface Client
{
    public function subscribe($callback, $topic);

    public function unsubscribe($callback, $topic);
}
