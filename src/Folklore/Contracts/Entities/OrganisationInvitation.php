<?php

namespace Folklore\Contracts\Entities;

use Carbon\Carbon;

interface OrganisationInvitation extends Entity
{
    public function organisation(): Organisation;

    public function token(): string;

    public function email(): string;

    public function role(): ?string;

    public function invitedAt(): Carbon;

    public function expiresAt(): ?Carbon;
}
