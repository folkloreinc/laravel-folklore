<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Folklore\Repositories\Users as BaseUsers;
use App\Contracts\Repositories\Users as UsersContract;
use App\Models\User as UserModel;

class Users extends BaseUsers implements UsersContract
{
    public function newModel(): Model
    {
        return new UserModel();
    }

    public function getModel(): Model
    {
        return new UserModel();
    }
}
