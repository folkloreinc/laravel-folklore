<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Resources\Contact;
use Folklore\Contracts\Resources\Resource;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Contracts\Translation\HasLocalePreference;

interface Customer extends Contact, Resource, HasSubscriptionPreferences, HasLocalePreference
{
    public function createdAt(): ?Carbon;
}
