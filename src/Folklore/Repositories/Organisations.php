<?php

namespace Folklore\Repositories;

use Folklore\Contracts\Repositories\Organisations as OrganisationsContract;
use Folklore\Contracts\Entities\Organisation as OrganisationContract;
use Folklore\Contracts\Entities\OrganisationMember as OrganisationMemberContract;
use Folklore\Contracts\Entities\User as UserContract;
use Illuminate\Database\Eloquent\Model;
use Folklore\Models\Organisation as OrganisationModel;
use Folklore\Models\OrganisationMember as OrganisationMemberModel;

class Organisations extends Entities implements OrganisationsContract
{
    protected function newModel(): Model
    {
        return new OrganisationModel();
    }

    protected function newMemberModel(): Model
    {
        return new OrganisationMemberModel();
    }

    public function findById(string $id): ?OrganisationContract
    {
        return parent::findById($id);
    }

    public function findBySlug(string $slug): ?OrganisationContract
    {
        $model = $this->newQueryWithParams()
            ->where('slug', 'LIKE', $slug)
            ->first();
        return to_entity($model);
    }

    public function create($data): OrganisationContract
    {
        return parent::create($data);
    }

    public function update(string $id, $data): ?OrganisationContract
    {
        return parent::update($id, $data);
    }

    public function addMemberFromUser(
        string $id,
        UserContract $user,
        $data
    ): ?OrganisationMemberContract {
        $model = $this->findModelById($id);
        if (is_null($model)) {
            return null;
        }

        $member = $model
            ->members()
            ->where('user_id', $user->id())
            ->first();
        if (!isset($member)) {
            $member = $this->newMemberModel();
            $member->organisation_id = $model->id;
            $member->user_id = $user->id();
        }
        $member->fill($data);
        $member->save();

        return to_entity($member);
    }
}
