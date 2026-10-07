<?php

namespace Folklore\Repositories;

use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\User as UserContract;
use Folklore\Contracts\Repositories\Users as UsersContract;
use Folklore\Models\User as UserModel;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class Users extends Entities implements UsersContract
{
    protected $userProvider;

    public function __construct(Hasher $hasher)
    {
        $this->userProvider = new EloquentUserProvider($hasher, get_class($this->newModel()));
    }

    protected function newModel(): Model
    {
        return new UserModel;
    }

    public function findById(string $id): ?UserContract
    {
        return parent::findById($id);
    }

    public function findByEmail(string $email): ?UserContract
    {
        $model = $this->whereLikeLiteral($this->newQuery(), 'email', $email)->first();

        return to_entity($model);
    }

    public function create($data): UserContract
    {
        return parent::create($data);
    }

    public function update(string $id, $data): ?UserContract
    {
        return parent::update($id, $data);
    }

    protected function fillModel($model, $data)
    {
        // The role is not mass assignable on the model, so that it can't be
        // set from unvalidated input; the repository still accepts it.
        if ($model instanceof UserModel && array_key_exists('role', $data)) {
            $model->forceFill(['role' => $data['role']]);
            $data = Arr::except($data, ['role']);
        }

        parent::fillModel($model, $data);

        if (isset($data['password']) && ! empty($data['password'])) {
            $model->password = Hash::make($data['password']);
        }
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $model = $this->userProvider->retrieveById($identifier);

        return to_entity($model);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $model = $this->userProvider->retrieveByToken($identifier, $token);

        return to_entity($model);
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        if (! is_null($user)) {
            $id = $user instanceof UserContract ? $user->id() : $user->id;
            $model = $this->findModelById($id);

            return $this->userProvider->updateRememberToken($model, $token);
        }
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $model = $this->userProvider->retrieveByCredentials($credentials);

        return to_entity($model);
    }

    /**
     * Validate a user against the given credentials.
     *
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return $this->userProvider->validateCredentials($user, $credentials);
    }

    /**
     * Rehash the user's password if required, on the model behind the entity.
     *
     * @return void
     */
    public function rehashPasswordIfRequired(
        Authenticatable $user,
        #[\SensitiveParameter] array $credentials,
        bool $force = false
    ) {
        if ($user instanceof HasModel) {
            $user = $user->getModel();
        }

        $this->userProvider->rehashPasswordIfRequired($user, $credentials, $force);
    }
}
