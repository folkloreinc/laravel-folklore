<?php

namespace Folklore\Contracts\Entities;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;

interface User extends Entity, Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail
{
    public function name(): ?string;

    public function email(): ?string;

    public function role(): ?string;
}
