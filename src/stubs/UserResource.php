<?php

namespace App\Resources;

use Folklore\Resources\User as BaseUser;
use App\Contracts\Resources\User as UserContract;
use App\Models\User as UserModel;

class User extends BaseUser implements UserContract
{
    public function __construct(UserModel $model)
    {
        $this->model = $model;
    }
}
