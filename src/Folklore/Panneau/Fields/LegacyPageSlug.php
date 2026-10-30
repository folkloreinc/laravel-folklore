<?php

namespace Folklore\Panneau\Fields;

use Closure;

/**
 * `PageSlug` without Panneau 5. A site that upgrades from 1.1 keeps laravel-panneau 1.x
 * until it moves its admin, and `PageSlug` extends Panneau 5's `Slug`, so it can't load:
 * the 1.x admin's call to `setRoutesResolver()` at boot would stop artisan, and
 * `folklore:upgrade --check` with it. PageSlug.php aliases this class instead.
 *
 * @internal
 */
final class LegacyPageSlug
{
    /**
     * 1.x's routes of the page slug field. Does nothing: 2.0's `PageSlug` builds the URL from
     * `site.pages.routes` (docs/decisions/0022), and only exists with Panneau 5.
     *
     * @deprecated Remove the call when the admin moves to Panneau 5. Removed in 3.0.
     */
    public static function setRoutesResolver(Closure $resolver): void
    {
        trigger_error(
            'PageSlug::setRoutesResolver() does nothing in laravel-folklore 2: remove the call. The URL of the slug comes from `site.pages.routes`, or `url()` on the field.',
            E_USER_DEPRECATED,
        );
    }
}
