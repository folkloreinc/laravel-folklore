<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Resources\Contact;
use Folklore\Contracts\Resources\Resource;
use Illuminate\Support\Collection;
use Carbon\Carbon;

interface Customer extends Contact, Resource, HasSubscriptionPreferences
{
    public function createdAt(): ?Carbon;
}
