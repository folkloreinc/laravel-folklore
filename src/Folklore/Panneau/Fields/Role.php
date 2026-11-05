<?php

namespace Folklore\Panneau\Fields;

use Folklore\Contracts\Entities\User as UserContract;
use Folklore\Panneau\Roles\DefaultRoles;
use Illuminate\Container\Container;
use Panneau\Fields\Select;
use Panneau\Roles\RoleManager;

/**
 * The role of a user: one of the roles of the roles module of `laravel-panneau`
 * (`panneau.roles.roles`), or of `DefaultRoles` when the module is off, stored in the user's
 * `role` column by the users repository (docs/decisions/0025).
 *
 * A role's label is its translation (`folklore::panneau.roles.{id}`) when the package has
 * one, else the label of the config. With the roles module, only users with the
 * `roles.assign` permission can change it.
 */
class Role extends Select
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $options = [];
        foreach (self::roles() as $id => $label) {
            $key = 'folklore::panneau.roles.'.$id;
            $translated = __($key);
            $options[$id] = is_string($translated) && $translated !== $key ? $translated : $label;
        }
        $this->options($options);
        $this->resolveUsing(fn (object $user): mixed => $user instanceof UserContract ? $user->role() : data_get($user, $this->name));
        if (self::rolesModule()) {
            $this->canUpdate(RoleManager::ASSIGN);
        }
    }

    /**
     * The roles, by id, with their label of the config.
     *
     * @return array<string, string>
     */
    protected static function roles(): array
    {
        $roles = [];
        if (self::rolesModule()) {
            foreach (Container::getInstance()->make(RoleManager::class)->roles() as $id => $role) {
                $roles[$id] = $role['label'];
            }

            return $roles;
        }
        foreach (DefaultRoles::ROLES as $id => $role) {
            $roles[$id] = $role['label'];
        }

        return $roles;
    }

    /**
     * Whether the roles module of `laravel-panneau` is on.
     */
    protected static function rolesModule(): bool
    {
        return Container::getInstance()->make('config')->get('panneau.roles.enabled') === true;
    }
}
