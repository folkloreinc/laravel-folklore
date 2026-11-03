<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\Block as BlockContract;
use Folklore\Contracts\Entities\HasBlocks;
use Folklore\Contracts\Pages\DeclaresBlockSchema;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Pages\BlockLevel;
use Folklore\Pages\BlockRegistry;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;

/**
 * A block of a page, read from its object in the page's JSON (docs/decisions/0023): its
 * `id`, `type` and `handle`, and its data, with its references resolved by the cast. The
 * class of a block type extends it and declares the schema of its data.
 */
class Block implements BlockContract, DeclaresBlockSchema, HasBlocks
{
    /**
     * @param  array<array-key, mixed>  $data  The block's object.
     */
    public function __construct(
        protected array $data,
    ) {}

    /**
     * The properties of the type's data. A block of no registered type has none, and keeps
     * its keys unless the map is enforced.
     */
    public static function blockSchema(BlockLevel $level): ObjectType
    {
        return Json::object([]);
    }

    public function id(): string
    {
        $id = $this->data['id'] ?? null;

        return is_string($id) || is_int($id) ? (string) $id : '';
    }

    public function type(): string
    {
        $type = $this->data['type'] ?? null;

        return is_string($type) ? $type : '';
    }

    public function handle(): ?string
    {
        $handle = $this->data['handle'] ?? null;

        return is_string($handle) ? $handle : null;
    }

    public function data(): ?array
    {
        return $this->data;
    }

    /**
     * The block's own blocks, as entities of their types.
     *
     * @return Collection<int, BlockContract>
     */
    public function blocks(): Collection
    {
        return once(fn () => Container::getInstance()->make(BlockRegistry::class)->toEntities($this->data['blocks'] ?? []));
    }
}
