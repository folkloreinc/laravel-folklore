<?php

namespace Folklore\Contracts\Resources;

use Illuminate\Support\Collection;

interface Organisation extends Resource
{
    public function name(): string;

    public function slug(): string;

    public function users(): ?Collection;

    public function invitations(): ?Collection;
}
