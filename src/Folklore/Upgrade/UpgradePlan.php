<?php

namespace Folklore\Upgrade;

/**
 * What `folklore:upgrade` changes in a site, and what it leaves to do.
 */
final readonly class UpgradePlan
{
    /**
     * @param  list<ConfigChange>  $config  The values to set in `config/site.php`.
     * @param  list<MediaPivotRows>  $mediaPivotRows  The rows of `medias_pivot` to remove.
     * @param  list<Finding>  $notes  What the command doesn't do, and why.
     */
    public function __construct(
        public array $config = [],
        public array $mediaPivotRows = [],
        public array $notes = [],
    ) {}

    public function isEmpty(): bool
    {
        return count($this->config) === 0 && count(array_filter($this->mediaPivotRows, fn (MediaPivotRows $rows) => $rows->count > 0)) === 0;
    }
}
