<?php

namespace Folklore\Models;

use Illuminate\Database\Eloquent\Model;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Contracts\Resources\OrganisationMember as OrganisationMemberContract;
use Folklore\Resources\OrganisationMember as OrganisationMemberResource;

class OrganisationMember extends Model implements Resourcable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['organisation_id', 'user_id', 'role'];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function toResource(): OrganisationMemberContract
    {
        return new OrganisationMemberResource($this);
    }
}
