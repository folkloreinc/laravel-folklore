<?php

namespace Folklore\Contracts\Entities;

use Illuminate\Contracts\Translation\HasLocalePreference;

interface Contact extends HasLocalePreference, Person
{
    public function email(): ?string;

    public function phone(): ?string;
}
