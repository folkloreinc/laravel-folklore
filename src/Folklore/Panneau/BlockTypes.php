<?php

namespace Folklore\Panneau;

use Closure;
use Folklore\Contracts\Pages\DeclaresBlockFields;
use Folklore\Pages\BlockRegistry;
use Illuminate\Container\Container;
use Panneau\Fields\BlockType;

/**
 * The types of blocks of the site, which the `Blocks` fields offer when they aren't given
 * types of their own (docs/decisions/0022). A singleton of the panneau module.
 *
 * The types of the pages module (`site.pages.blocks.types`) whose entity class declares its
 * fields (`DeclaresBlockFields`, as the standard block types do) come first, in their order
 * (docs/decisions/0025). Other types are registered as Panneau `BlockType`s, or as closures
 * that return some, called when a field first needs them: in a request, so that their labels
 * are translated in its locale. A registered type replaces a declared type of the same id.
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
     * The types: those the entity classes of the pages module declare, then the registered
     * ones, in the order they were registered.
     *
     * @return list<BlockType>
     */
    public function all(): array
    {
        $types = [];
        foreach ($this->declared() as $type) {
            $types[$type->id()] = $type;
        }
        foreach ($this->registered as $registered) {
            $resolved = $registered instanceof Closure ? $registered() : $registered;
            foreach ($resolved instanceof BlockType ? [$resolved] : $resolved as $type) {
                $types[$type->id()] = $type;
            }
        }

        return array_values($types);
    }

    /**
     * The types of `site.pages.blocks.types` whose entity class declares its fields.
     *
     * @return list<BlockType>
     */
    protected function declared(): array
    {
        $container = Container::getInstance();
        if (! $container->bound(BlockRegistry::class)) {
            return [];
        }

        $types = [];
        foreach ($container->make(BlockRegistry::class)->types() as $id => $class) {
            if (! is_subclass_of($class, DeclaresBlockFields::class)) {
                continue;
            }
            $type = BlockType::make($id, $class::blockLabel(), $class::blockFields());
            $icon = $class::blockIcon();
            $types[] = $icon !== null ? $type->icon($icon) : $type;
        }

        return $types;
    }

    /**
     * The ids of the types.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(fn (BlockType $type): string => $type->id(), $this->all());
    }

    /**
     * Forgets the registered types. The declared types stay: they are the pages module's.
     */
    public function flush(): void
    {
        $this->registered = [];
    }
}
