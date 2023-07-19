<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\HasModel;
use Folklore\Contracts\Resources\OrganisationMember as OrganisationMemberContract;
use Folklore\Contracts\Resources\Organisation as OrganisationContract;
use Folklore\Contracts\Resources\User as UserContract;
use Folklore\Models\OrganisationMember as OrganisationMemberModel;
use Folklore\Contracts\Resources\Resourcable;
use Illuminate\Database\Eloquent\Model;

class OrganisationMember implements OrganisationMemberContract, HasModel
{
    protected $model;

    protected $user;

    protected $organisation;

    public function __construct(OrganisationMemberModel $model, OrganisationContract $organisation = null)
    {
        $this->model = $model;
        $this->organisation = $organisation;
    }

    public function id(): string
    {
        return $this->model->id;
    }

    public function role(): ?string
    {
        return $this->model->role;
    }

    public function organisation(): OrganisationContract
    {
        if (!isset($this->organisation)) {
            $model = $this->model->organisation;
            $this->organisation = $model instanceof Resourcable ? $model->toResource() : $model;
        }
        return $this->organisation;
    }

    public function user(): UserContract
    {
        if (!isset($this->user)) {
            $model = $this->model->user;
            $this->user = $model instanceof Resourcable ? $model->toResource() : $model;
        }
        return $this->user;
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
