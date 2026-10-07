<?php

namespace Folklore\Models;

use Folklore\Contracts\Entities\Organisation as OrganisationContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Entities\Organisation as OrganisationEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organisation extends Model implements ToEntity
{
    use SoftDeletes;

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
