<?php

namespace Folklore\Panneau\Fields;

use Folklore\Panneau\Fields\Concerns\KeepsRoutesResolver;

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
    use KeepsRoutesResolver;
}
