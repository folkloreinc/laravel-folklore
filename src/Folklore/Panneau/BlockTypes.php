<?php

namespace Folklore\Panneau;

use Closure;
use Panneau\Fields\BlockType;

/**
 * The types of blocks of the site, which the `Blocks` fields offer when they aren't given
 * types of their own (docs/decisions/0022). A singleton of the panneau module.
 *
 * Types are registered as Panneau `BlockType`s, or as closures that return some, called when
 * a field first needs them: in a request, so that their labels are translated in its locale.
 *
 * ```php
 * $this->app->make(BlockTypes::class)->register(fn () => [
 *     BlockType::make('text', __('Text'), [RichText::make('body')->localized()]),
 *     BlockType::make('stack', __('Stack'), [Blocks::make('blocks')->exceptTypes('stack')]),
 * ]);
 * ```
 */
class BlockTypes
{
    /**
     * @var list<BlockType|(Closure(): (BlockType|iterable<BlockType>))>
     */
    protected array $registered = [];

    /**
     * Registers types. A type registered again under the same id replaces the previous one,
     * at its place.
     *
     * @param  BlockType|iterable<BlockType>|(Closure(): (BlockType|iterable<BlockType>))  $types
     */
    public function register(BlockType|iterable|Closure $types): static
    {
        if ($types instanceof BlockType || $types instanceof Closure) {
            $this->registered[] = $types;

            return $this;
        }
        foreach ($types as $type) {
            $this->registered[] = $type;
        }

        return $this;
    }

    /**
     * The registered types, in the order they were registered.
     *
     * @return list<BlockType>
     */
    public function all(): array
    {
        $types = [];
        foreach ($this->registered as $registered) {
            $resolved = $registered instanceof Closure ? $registered() : $registered;
            foreach ($resolved instanceof BlockType ? [$resolved] : $resolved as $type) {
                $types[$type->id()] = $type;
            }
        }

        return array_values($types);
    }

    /**
     * The ids of the registered types.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(fn (BlockType $type): string => $type->id(), $this->all());
    }

    /**
     * Forgets the registered types.
     */
    public function flush(): void
    {
        $this->registered = [];
    }
}
