<?php

namespace Folklore\Models;

use Illuminate\Database\Eloquent\Model;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Contracts\Entities\OrganisationMember as OrganisationMemberContract;
use Folklore\Entities\OrganisationMember as OrganisationMemberEntity;

class OrganisationMember extends Model implements ToEntity
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

    public function toEntity(): OrganisationMemberContract
    {
        return new OrganisationMemberEntity($this);
    }
}
