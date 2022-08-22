<?php

namespace Folklore\Repositories;

use Folklore\Contracts\Repositories\Users as UsersContract;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Contracts\Resources\Resource;
use Folklore\Contracts\Resources\User as UserContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Model;
use Folklore\Models\User as UserModel;

class Users extends Resources implements UsersContract
{
    protected $userProvider;

    public function __construct(Hasher $hasher)
    {
        $this->userProvider = new EloquentUserProvider($hasher, get_class($this->newModel()));
    }

    protected function newModel(): Model
    {
        return new UserModel();
    }

    public function findById(string $id): ?UserContract
    {
        return parent::findById($id);
    }

    public function findByEmail(string $email): ?UserContract
    {
        $model = $this->newQuery()
            ->where('email', 'LIKE', $email)
            ->first();
        return $model instanceof Resourcable ? $model->toResource() : $model;
    }

    public function create(array $data): UserContract
    {
        return parent::create($data);
    }

    public function update(string $id, array $data): ?UserContract
    {
        return parent::update($id, $data);
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $model = $this->userProvider->retrieveById($identifier);
        return !is_null($model) && $model instanceof Resourcable ? $model->toResource() : null;
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $model = $this->userProvider->retrieveByToken($identifier, $token);
        return !is_null($model) && $model instanceof Resourcable ? $model->toResource() : null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        if (!is_null($user)) {
            $id = $user instanceof UserContract ? $user->id() : $user->id;
            $model = $this->findModelById($id);
            return $this->userProvider->updateRememberToken($model, $token);
        }
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $model = $this->userProvider->retrieveByCredentials($credentials);

        $resource = !is_null($model) && $model instanceof Resourcable ? $model->toResource() : null;

        return $resource;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return $this->userProvider->validateCredentials($user, $credentials);
    }
}
