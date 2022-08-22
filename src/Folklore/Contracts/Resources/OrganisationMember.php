<?php

namespace Folklore\Contracts\Resources;

use Carbon\Carbon;
use Folklore\Contracts\Resources\Resource;

interface OrganisationMember extends Resource
{
    public function organisation(): Organisation;

    public function user(): User;

    public function role(): ?string;
}
