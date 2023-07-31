<?php

namespace Folklore\Contracts\Services\PubSubHubbub;

interface Factory
{
    public function hub($hub = null): Client;
}
