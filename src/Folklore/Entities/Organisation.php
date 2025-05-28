<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\Organisation as OrganisationContract;
use Folklore\Models\Organisation as OrganisationModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Organisation implements OrganisationContract, HasModel
{
    protected $model;

    protected $members;

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

    public function members(): Collection
    {
        if (!isset($this->members)) {
            $this->members = $this->model->members->toBase()->map(function ($item) {
                return to_entity($item);
            });
        }
        return $this->members;
    }

    public function invitations(): Collection
    {
        if (!isset($this->invitations)) {
            $this->invitations = $this->model->invitations->toBase()->map(function ($item) {
                return to_entity($item);
            });
        }
        return $this->invitations;
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
