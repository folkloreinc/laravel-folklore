<?php

namespace Folklore\Upgrade;

/**
 * What `folklore:upgrade` converts in the data of the pages (docs/decisions/0023): the
 * `blocks://` references of their blocks into the blocks' objects, and their `parent://`
 * references into `page://`.
 */
final readonly class BlocksConversion
{
    /**
     * @param  class-string  $model  The pages model.
     * @param  int  $pages  The pages whose data changes.
     * @param  int  $blocks  The blocks put into their page, at every level.
     * @param  int  $parents  The `parent://` references rewritten.
     * @param  int  $missing  The `blocks://` references to rows that don't exist, left out.
     * @param  int  $left  The rows of `blocks_pivot` of blocks that no page's data references, or of other models: not converted.
     */
    public function __construct(
        public string $model,
        public int $pages = 0,
        public int $blocks = 0,
        public int $parents = 0,
        public int $missing = 0,
        public int $left = 0,
    ) {}

    public function isEmpty(): bool
    {
        return $this->pages === 0;
    }
}
