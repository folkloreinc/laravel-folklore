<?php

namespace Folklore\Pages;

use Folklore\Contracts\Entities\Block as BlockContract;
use Folklore\Contracts\Pages\DeclaresBlockSchema;
use Folklore\Entities\Block;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\UnionType;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The block types of the pages module, `site.pages.blocks.types` (docs/decisions/0023):
 * the entity class of each type, which gives the schema of its data. A page's blocks are
 * a list of their union, built down to `site.pages.blocks.depth` levels, and read back as
 * entities of their types.
 */
class BlockRegistry
{
    /**
     * The properties of every type, beside its own: written by the union (`type`) and by
     * the pages repository (`id`).
     */
    public const COMMON_PROPERTIES = ['id', 'handle'];

    /**
     * The default deepest level of blocks, as the `Blocks` field of the Panneau plugin.
     */
    public const DEPTH = 3;

    /**
     * @var array<int, UnionType|null>
     */
    private array $unions = [];

    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * The entity class of each type.
     *
     * @return array<string, class-string<BlockContract&DeclaresBlockSchema>>
     */
    public function types(): array
    {
        $types = [];
        foreach ((array) $this->config->get('site.pages.blocks.types', []) as $type => $class) {
            if (! is_string($class) || ! is_subclass_of($class, BlockContract::class) || ! is_subclass_of($class, DeclaresBlockSchema::class)) {
                throw new InvalidArgumentException("The block type [{$type}] of site.pages.blocks.types is not a block entity class that declares its schema (Folklore\\Entities\\Block).");
            }
            $types[(string) $type] = $class;
        }

        return $types;
    }

    public function depth(): int
    {
        $depth = $this->config->get('site.pages.blocks.depth', self::DEPTH);

        return is_int($depth) && $depth > 0 ? $depth : self::DEPTH;
    }

    /**
     * The union of the types at a level, from 1 for a page's blocks, with `id` and
     * `handle` in each; null when no type is offered there.
     */
    public function union(int $level = 1): ?UnionType
    {
        if (array_key_exists($level, $this->unions)) {
            return $this->unions[$level];
        }

        $variants = [];
        foreach ($this->types() as $type => $class) {
            $blockLevel = new BlockLevel($this, $level, $this->depth());
            $schema = $class::blockSchema($blockLevel);
            if ($blockLevel->exceeded()) {
                continue;
            }
            $variants[$type] = Json::object([
                'id' => Json::string(),
                'handle' => Json::string()->nullable(),
                ...$schema->getProperties(),
            ])->additional($schema->allowsAdditional());
        }

        return $this->unions[$level] = count($variants) > 0 ? Json::union('type', $variants) : null;
    }

    /**
     * The entity of a block's object: of its type's class, or a `Block`.
     *
     * @param  array<array-key, mixed>  $block
     */
    public function toEntity(array $block): BlockContract
    {
        $type = $block['type'] ?? null;
        $class = is_string($type) ? ($this->types()[$type] ?? Block::class) : Block::class;

        return new $class($block);
    }

    /**
     * The entities of a list of blocks; what isn't a block's object is left out.
     *
     * @return Collection<int, BlockContract>
     */
    public function toEntities(mixed $blocks): Collection
    {
        $entities = [];
        foreach (is_iterable($blocks) ? $blocks : [] as $block) {
            if ($block instanceof BlockContract) {
                $entities[] = $block;
            } elseif (is_array($block)) {
                $entities[] = $this->toEntity($block);
            }
        }

        return collect($entities);
    }

    /**
     * Gives an id to each block that has none, at every level: `blk_` and a ULID.
     *
     * @param  array<array-key, mixed>  $blocks
     * @return array<array-key, mixed>
     */
    public function withIds(array $blocks): array
    {
        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }
            $id = $block['id'] ?? null;
            if ((! is_string($id) && ! is_int($id)) || (string) $id === '') {
                $block = ['id' => 'blk_'.strtolower((string) Str::ulid()), ...$block];
            } else {
                $block['id'] = (string) $id;
            }
            if (is_array($block['blocks'] ?? null)) {
                $block['blocks'] = $this->withIds($block['blocks']);
            }
            $blocks[$index] = $block;
        }

        return $blocks;
    }
}
