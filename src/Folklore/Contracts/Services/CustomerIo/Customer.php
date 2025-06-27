<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Entities\Contact;
use Folklore\Contracts\Entities\Entity;
use Carbon\Carbon;
use Illuminate\Contracts\Translation\HasLocalePreference;

interface Customer extends Contact, Entity, HasSubscriptionPreferences, HasLocalePreference
{
    public function externalId(): string;

    public function createdAt(): ?Carbon;

    public function attributes(): array;
}
