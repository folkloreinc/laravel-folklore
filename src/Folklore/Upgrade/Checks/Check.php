<?php

namespace Folklore\Upgrade\Checks;

use Folklore\Upgrade\Finding;
use Folklore\Upgrade\Site;

/**
 * A check of `folklore:upgrade --check`: what a 1.1 site does that changes in 2.0.
 */
interface Check
{
    /**
     * The title of the check's section in the report.
     */
    public function title(): string;

    /**
     * @return iterable<Finding>
     */
    public function check(Site $site): iterable;
}
