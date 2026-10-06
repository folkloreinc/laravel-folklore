<?php

namespace Folklore\Models;

use Folklore\Contracts\Entities\OrganisationInvitation as OrganisationInvitationContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Entities\OrganisationInvitation as OrganisationInvitationEntity;
use Illuminate\Database\Eloquent\Model;

class OrganisationInvitation extends Model implements ToEntity
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['organisation_id', 'email', 'role', 'token', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function toEntity(): OrganisationInvitationContract
    {
        return new OrganisationInvitationEntity($this);
    }
}
