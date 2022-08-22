<?php

namespace Folklore\Models;

use Illuminate\Database\Eloquent\Model;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Contracts\Resources\OrganisationInvitation as OrganisationInvitationContract;
use Folklore\Resources\OrganisationInvitation as OrganisationInvitationResource;

class OrganisationInvitation extends Model implements Resourcable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['organisation_id', 'email', 'role', 'token', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function toResource(): OrganisationInvitationContract
    {
        return new OrganisationInvitationResource($this);
    }
}
