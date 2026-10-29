<?php

namespace Folklore\Rector;

/**
 * The Rector sets of laravel-folklore, for `withSets()` in a site's `rector.php`
 * (docs/upgrade.md). Rector is a dev dependency of the site, not of this package.
 */
final class FolkloreSetList
{
    /**
     * The upgrade from 1.1 to 2.0: the classes and contracts that moved, and the 1.x API
     * that 2.0 changes in a way code can follow.
     */
    public const UPGRADE_2_0 = __DIR__.'/../../../rector/upgrade-2.0.php';
}
