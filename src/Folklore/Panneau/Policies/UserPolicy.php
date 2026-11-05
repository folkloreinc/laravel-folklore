<?php

namespace Folklore\Panneau\Policies;

use Panneau\Roles\ResourcePolicy;

/**
 * The policy of the `users` resource (`Folklore\Panneau\Resources\Users`), on the permissions of
 * the roles module of `laravel-panneau` (docs/decisions/0025). A site registers it, or a
 * policy that extends it.
 */
class UserPolicy extends ResourcePolicy
{
    protected string $resource = 'users';
}
