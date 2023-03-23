<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\HasModel;
use Folklore\Contracts\Resources\OrganisationUser as OrganisationUserContract;
use Folklore\Contracts\Resources\Organisation as OrganisationContract;
use Folklore\Contracts\Resources\User as UserContract;
use Folklore\Models\OrganisationUser as OrganisationUserModel;
use Folklore\Contracts\Resources\Resourcable;
use Illuminate\Database\Eloquent\Model;

class OrganisationUser implements OrganisationUserContract, HasModel
{
    protected $model;

    protected $user;

    protected $organisation;

    public function __construct(OrganisationUserModel $model, OrganisationContract $organisation = null)
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
