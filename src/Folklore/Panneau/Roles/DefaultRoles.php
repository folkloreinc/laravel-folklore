<?php

namespace Folklore\Panneau\Roles;

/**
 * The default roles of a site, for the roles module of `laravel-panneau`
 * (docs/decisions/0025): `admin`, who can do everything, roles included, and `editor`, who
 * manages the pages and the media library, but not the users.
 *
 * A site gives them to the module in its `config/panneau.php`, with the store of the users'
 * `role` column, and adds its own resources to the editor's permissions:
 *
 * ```php
 * 'roles' => [
 *     'enabled' => true,
 *     'store' => \Folklore\Panneau\Roles\UserRoleStore::class,
 *     'roles' => \Folklore\Panneau\Roles\DefaultRoles::ROLES,
 * ],
 * ```
 *
 * Their labels are translated by the `Role` field (`folklore::panneau.roles.*`).
 */
final class DefaultRoles
{
    public const ADMIN = 'admin';

    public const EDITOR = 'editor';

    /**
     * The roles, as `panneau.roles.roles` takes them.
     */
    public const ROLES = [
        self::ADMIN => [
            'label' => 'Administrator',
            'permissions' => ['*'],
        ],
        self::EDITOR => [
            'label' => 'Editor',
            'permissions' => ['pages.*', 'medias.*'],
        ],
    ];
}
