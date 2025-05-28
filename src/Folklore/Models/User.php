<?php

namespace Folklore\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Contracts\Entities\User as UserContract;
use Folklore\Entities\User as UserEntity;

class User extends Authenticatable implements ToEntity
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'email', 'password', 'role'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['created_at', 'updated_at', 'password', 'remember_token'];

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
