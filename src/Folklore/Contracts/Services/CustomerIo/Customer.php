<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Carbon\Carbon;
use Folklore\Contracts\Entities\Contact;
use Folklore\Contracts\Entities\Entity;
use Illuminate\Contracts\Translation\HasLocalePreference;

interface Customer extends Contact, Entity, HasLocalePreference, HasSubscriptionPreferences
{
    public function externalId(): string;

    public function createdAt(): ?Carbon;

    public function attributes(): array;
}
