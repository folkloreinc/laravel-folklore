<?php

namespace App\Contracts\Resources;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;

interface User extends
    Resource,
    Authorizable,
    Authenticatable,
    CanResetPassword,
    MustVerifyEmail,
    HasLocalePreference
{
    public function name(): string;

    public function email(): string;

    public function role(): string;
}
