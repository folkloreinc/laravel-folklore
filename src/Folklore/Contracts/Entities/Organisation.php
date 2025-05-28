<?php

namespace Folklore\Contracts\Entities;

use Illuminate\Support\Collection;

interface Organisation extends Entity
{
    public function name(): string;

    public function slug(): string;

    public function members(): ?Collection;

    public function invitations(): ?Collection;
}
