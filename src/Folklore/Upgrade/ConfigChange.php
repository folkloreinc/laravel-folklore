<?php

namespace Folklore\Upgrade;

/**
 * A value that `folklore:upgrade` sets in `config/site.php`.
 */
final readonly class ConfigChange
{
    /**
     * @param  string  $key  The key under `site`, dotted: `modules.medias`.
     * @param  string  $reason  Why the site needs it.
     * @param  list<string>  $locations  What in the site needs it: `path:line`, or a model
     *                                   and its JSON attribute.
     */
    public function __construct(
        public string $key,
        public bool|string $value,
        public string $reason,
        public array $locations = [],
    ) {}
}
