<?php

namespace Folklore\Upgrade;

/**
 * How much a finding of `folklore:upgrade --check` matters.
 */
enum Severity: string
{
    /**
     * Breaks in 2.0: fix it before upgrading.
     */
    case Breaking = 'breaking';

    /**
     * To do while upgrading: a module to enable, a package to require, data to convert.
     */
    case Todo = 'todo';

    /**
     * Keeps working in 2.0, deprecated until 3.0.
     */
    case Deprecated = 'deprecated';

    /**
     * Nothing to do.
     */
    case Info = 'info';

    /**
     * Whether the site has to act on it to upgrade.
     */
    public function blocks(): bool
    {
        return $this === self::Breaking || $this === self::Todo;
    }
}
