<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\OrganisationInvitation as OrganisationInvitationContract;
use Folklore\Contracts\Resources\Organisation as OrganisationContract;
use Folklore\Contracts\Resources\User as UserContract;
use Folklore\Models\OrganisationInvitation as OrganisationInvitationModel;
use Folklore\Contracts\Resources\Resourcable;
use Carbon\Carbon;
use Folklore\Contracts\Resources\HasModel;
use Illuminate\Database\Eloquent\Model;

class OrganisationInvitation implements OrganisationInvitationContract, HasModel
{
    protected $model;

    protected $user;

    protected $organisation;

    public function __construct(
        OrganisationInvitationModel $model,
        OrganisationContract $organisation = null
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
        if (!isset($this->organisation)) {
            $model = $this->model->organisation;
            $this->organisation = $model instanceof Resourcable ? $model->toResource() : $model;
        }
        return $this->organisation;
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
