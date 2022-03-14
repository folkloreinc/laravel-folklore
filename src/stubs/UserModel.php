<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
// use Laravel\Fortify\TwoFactorAuthenticatable;
// use Laravel\Sanctum\HasApiTokens;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Contracts\Resources\Resourcable;
use App\Contracts\Resources\User as UserContract;
use App\Resources\User as UserResource;

class User extends Authenticatable implements HasJsonDataRelations, Resourcable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'email', 'password'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'data' => JsonDataCast::class,
    ];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return [];
    }

    public function toResource(): UserContract
    {
        return new UserResource($this);
    }
}
