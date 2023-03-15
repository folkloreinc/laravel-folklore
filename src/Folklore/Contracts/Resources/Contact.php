<?php

namespace Folklore\Contracts\Resources;

use Illuminate\Contracts\Translation\HasLocalePreference;

interface Contact extends Person, HasLocalePreference
{
    public function email(): ?string;

    public function phone(): ?string;
}
