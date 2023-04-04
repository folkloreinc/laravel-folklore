<?php

namespace Folklore\Contracts\Resources;

use Folklore\Contracts\Resources\Resource;

interface OrganisationMember extends Resource
{
    public function organisation(): Organisation;

    public function user(): User;

    public function role(): ?string;
}
