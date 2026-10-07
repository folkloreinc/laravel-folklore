<?php

namespace Folklore\Models;

use Folklore\Contracts\Entities\ToEntity;
use Folklore\Contracts\Entities\User as UserContract;
use Folklore\Entities\User as UserEntity;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements ToEntity
{
    use Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable. The role is not: the users
     * repository sets it explicitly.
     *
     * @var array
     */
    protected $fillable = ['name', 'email', 'password'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function toEntity(): UserContract
    {
        return new UserEntity($this);
    }
}
