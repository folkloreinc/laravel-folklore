<?php

namespace Folklore\Repositories;

use Folklore\Contracts\Repositories\Organisations as OrganisationsContract;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Contracts\Resources\Organisation as OrganisationContract;
use Folklore\Contracts\Resources\OrganisationMember as OrganisationMemberContract;
use Folklore\Contracts\Resources\User as UserContract;
use Illuminate\Database\Eloquent\Model;
use Folklore\Models\Organisation as OrganisationModel;
use Folklore\Models\OrganisationMember as OrganisationMemberModel;

class Organisations extends Resources implements OrganisationsContract
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
        $model = $this->newQuery()
            ->where('slug', 'LIKE', $slug)
            ->first();
        return $model instanceof Resourcable ? $model->toResource() : $model;
    }

    public function create(array $data): OrganisationContract
    {
        return parent::create($data);
    }

    public function update(string $id, array $data): ?OrganisationContract
    {
        return parent::update($id, $data);
    }

    public function addMemberFromUser(
        string $id,
        UserContract $user,
        array $data
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

        return $member instanceof Resourcable ? $member->toResource() : $member;
    }
}
