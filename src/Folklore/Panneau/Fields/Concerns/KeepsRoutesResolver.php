<?php

namespace Folklore\Panneau\Fields\Concerns;

use Closure;

/**
 * 1.x's `PageSlug::setRoutesResolver()`, which the 1.x admin of a site calls at boot. 2.0's
 * `PageSlug` builds the URL from `site.pages.routes` (docs/decisions/0022), so the call does
 * nothing: it is kept so that the site boots while it upgrades, and
 * `folklore:upgrade --check` reports it.
 */
trait KeepsRoutesResolver
{
    /**
     * Does nothing.
     *
     * @deprecated Remove the call: `PageSlug` builds the URL from `site.pages.routes`, or
     *             `url()` on the field. Removed in 3.0.
     */
    public static function setRoutesResolver(Closure $resolver): void
    {
        trigger_error(
            'PageSlug::setRoutesResolver() does nothing in laravel-folklore 2: remove the call. The URL of the slug comes from `site.pages.routes`, or `url()` on the field.',
            E_USER_DEPRECATED,
        );
    }
}
