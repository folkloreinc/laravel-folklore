<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Illuminate\Support\Collection;

interface HasSubscriptionPreferences
{
    public function subscriptionPreferences(): Collection;
}
