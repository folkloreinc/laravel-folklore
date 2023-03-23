<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\HasModel;
use Folklore\Contracts\Resources\Organisation as OrganisationContract;
use Folklore\Models\Organisation as OrganisationModel;
use Folklore\Contracts\Resources\Resourcable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Organisation implements OrganisationContract, HasModel
{
    protected $model;

    protected $users;

    protected $invitations;

    public function __construct(OrganisationModel $model)
    {
        $this->model = $model;
    }

    public function id(): string
    {
        return $this->model->id;
    }

    public function name(): string
    {
        return $this->model->name;
    }

    public function slug(): string
    {
        return $this->model->slug;
    }

    public function users(): Collection
    {
        if (!isset($this->users)) {
            $this->users = $this->model->users->map(function ($item) {
                return $item instanceof Resourcable ? $item->toResource() : $item;
            });
        }
        return $this->users;
    }

    public function invitations(): Collection
    {
        if (!isset($this->invitations)) {
            $this->invitations = $this->model->invitations->map(function ($item) {
                return $item instanceof Resourcable ? $item->toResource() : $item;
            });
        }
        return $this->invitations;
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
