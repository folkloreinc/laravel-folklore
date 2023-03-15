<?php

namespace Folklore\Contracts\Resources;

use Carbon\Carbon;

interface Person
{
    public function name(): ?string;

    public function firstName(): ?string;

    public function lastName(): ?string;

    public function birthdate(): ?Carbon;
}
