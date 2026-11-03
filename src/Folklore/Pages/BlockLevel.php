<?php

namespace Folklore\Pages;

use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ListType;

/**
 * A level of blocks, given to the schema of each type (`DeclaresBlockSchema`): a type that
 * holds blocks gets them from `blocks()`, the blocks of the next level. Below the depth, a
 * type that asks for them isn't offered, as Panneau's `Blocks` field doesn't offer it
 * (docs/decisions/0022).
 */
final class BlockLevel
{
    private bool $exceeded = false;

    /**
     * @param  int  $level  The level, from 1 for the blocks of a page.
     * @param  int  $depth  The deepest level.
     */
    public function __construct(
        private readonly BlockRegistry $registry,
        public readonly int $level,
        public readonly int $depth,
    ) {}

    /**
     * The blocks of a block: a list of the next level's types.
     */
    public function blocks(): ListType
    {
        $union = $this->level < $this->depth ? $this->registry->union($this->level + 1) : null;
        if (is_null($union)) {
            $this->exceeded = true;

            return Json::list(Json::any());
        }

        return Json::list($union);
    }

    /**
     * Whether the type asked for blocks deeper than the depth, where it isn't offered.
     */
    public function exceeded(): bool
    {
        return $this->exceeded;
    }
}
