<?php

namespace Folklore\Panneau\Policies;

use Panneau\Roles\ResourcePolicy;

/**
 * The policy of the `pages` resource (`Folklore\Panneau\Resources\Pages`), on the permissions of
 * the roles module of `laravel-panneau` (docs/decisions/0025). A site registers it, or a
 * policy that extends it.
 */
class PagePolicy extends ResourcePolicy
{
    protected string $resource = 'pages';
}
