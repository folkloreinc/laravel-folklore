<?php

namespace Folklore\Panneau\Roles;

use Folklore\Contracts\Entities\HasModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Panneau\Roles\Contracts\RoleStore;

/**
 * The store of the roles module of `laravel-panneau` on the `role` column of the users, which
 * the package's users have since 1.x (docs/decisions/0025): a user has one role, and no
 * grants. `panneau.roles.store` names it.
 */
class UserRoleStore implements RoleStore
{
    /**
     * The role of a user without one: the default of the `role` column of the package's
     * migration, which no role of the config has.
     */
    public const NO_ROLE = 'guest';

    public function roles(Authenticatable $user): array
    {
        $role = $this->modelOf($user)?->getAttribute('role');

        return is_string($role) && $role !== '' && $role !== static::NO_ROLE ? [$role] : [];
    }

    public function grants(Authenticatable $user): array
    {
        return [];
    }

    /**
     * Keeps the first role: a user has one. Without, the user gets `NO_ROLE`.
     */
    public function syncRoles(Authenticatable $user, array $roles): void
    {
        $model = $this->modelOf($user)
            ?? throw new LogicException(sprintf('%s keeps the roles of users on their model: %s has none.', static::class, $user::class));
        $model->forceFill(['role' => $roles[0] ?? static::NO_ROLE])->save();
    }

    public function syncGrants(Authenticatable $user, array $permissions): void
    {
        if ($permissions !== []) {
            throw new LogicException(sprintf('%s keeps no grants: users have one role.', static::class));
        }
    }

    /**
     * The model of a user: the user, or the model of an entity.
     */
    protected function modelOf(Authenticatable $user): ?Model
    {
        if ($user instanceof Model) {
            return $user;
        }

        return $user instanceof HasModel ? $user->getModel() : null;
    }
}
