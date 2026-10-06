<?php

namespace Folklore\Entities;

use Carbon\Carbon;
use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\Organisation as OrganisationContract;
use Folklore\Contracts\Entities\OrganisationInvitation as OrganisationInvitationContract;
use Folklore\Models\OrganisationInvitation as OrganisationInvitationModel;
use Illuminate\Database\Eloquent\Model;

class OrganisationInvitation implements HasModel, OrganisationInvitationContract
{
    protected $model;

    protected $user;

    protected $organisation;

    public function __construct(
        OrganisationInvitationModel $model,
        ?OrganisationContract $organisation = null
    ) {
        $this->model = $model;
        $this->organisation = $organisation;
    }

    public function id(): string
    {
        return $this->model->id;
    }

    public function token(): string
    {
        return $this->model->token;
    }

    public function email(): string
    {
        return $this->model->email;
    }

    public function role(): ?string
    {
        return $this->model->role;
    }

    public function invitedAt(): Carbon
    {
        return $this->model->created_at;
    }

    public function expiresAt(): ?Carbon
    {
        return $this->model->expires_at;
    }

    public function organisation(): OrganisationContract
    {
        if (! isset($this->organisation)) {
            $model = $this->model->organisation;
            $this->organisation = to_entity($model);
        }

        return $this->organisation;
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
