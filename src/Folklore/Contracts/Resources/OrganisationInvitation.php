<?php

namespace Folklore\Contracts\Resources;

use Carbon\Carbon;
use Folklore\Contracts\Resources\Resource;

interface OrganisationInvitation extends Resource
{
    public function organisation(): Organisation;

    public function token(): string;

    public function email(): string;

    public function role(): ?string;

    public function invitedAt(): Carbon;

    public function expiresAt(): ?Carbon;
}
