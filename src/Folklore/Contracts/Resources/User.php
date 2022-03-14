<?php

namespace Folklore\Contracts\Resources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;

interface User extends Resource, Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail
{
    public function name(): ?string;

    public function email(): ?string;

    public function password(): ?string;
}
