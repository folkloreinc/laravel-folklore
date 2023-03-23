<?php

namespace Folklore\Models;

use Illuminate\Database\Eloquent\Model;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Contracts\Resources\Organisation as OrganisationContract;
use Folklore\Resources\Organisation as OrganisationResource;

class Organisation extends Model implements Resourcable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'slug'];

    public function users()
    {
        return $this->hasMany(OrganisationUsers::class);
    }

    public function invitations()
    {
        return $this->hasMany(OrganisationInvitation::class);
    }

    public function toResource(): OrganisationContract
    {
        return new OrganisationResource($this);
    }
}
