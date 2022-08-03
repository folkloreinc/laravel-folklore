<?php

namespace Folklore\Contracts\Resources;

use Carbon\Carbon;
use Folklore\Contracts\Resources\Resource;

interface Member extends Resource
{
    public function organisation(): Organisation;

    public function user(): ?User;

    public function role(): string;

    public function email(): string;

    public function isInvitation(): bool;

    public function invitationToken(): ?string;

    public function invitedAt(): ?Carbon;
}
