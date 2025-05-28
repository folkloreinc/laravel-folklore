<?php

namespace Folklore\Models;

use Illuminate\Database\Eloquent\Model;
use Folklore\Contracts\Entities\Organisation as OrganisationContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Entities\Organisation as OrganisationEntity;

class Organisation extends Model implements ToEntity
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'slug'];

    public function members()
    {
        return $this->hasMany(OrganisationMember::class);
    }

    public function invitations()
    {
        return $this->hasMany(OrganisationInvitation::class);
    }

    public function toEntity(): OrganisationContract
    {
        return new OrganisationEntity($this);
    }
}
