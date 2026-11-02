<?php

namespace Folklore\Upgrade;

/**
 * A node of the schema that `JsonSchemaConverter` builds from the 1.x declarations of a
 * model, and what the stored data says about it.
 *
 * @internal
 */
final class SchemaNode
{
    public const OBJECT = 'object';

    /**
     * A `*` segment: a list, a map or the locales of a translatable node.
     */
    public const ITEMS = 'items';

    public const REFERENCE = 'reference';

    /**
     * A value copied into a column.
     */
    public const SCALAR = 'scalar';

    /**
     * References at their 1.x paths under the node, which isn't described:
     * `Json::any()->paths([...])`.
     */
    public const PATHS = 'paths';

    /**
     * @var array<string, SchemaNode>
     */
    public array $properties = [];

    public ?SchemaNode $items = null;

    /**
     * The references of a `PATHS` node, by their path from it.
     *
     * @var array<string, string>
     */
    public array $paths = [];

    /**
     * The code of a reference node: `Json::media()`, `Json::reference('prize')->relation('prizes')`.
     */
    public ?string $reference = null;

    public ?string $column = null;

    /**
     * What the stored data holds at an `ITEMS` node: arrays that are lists, objects keyed
     * by the site's locales, and other objects.
     */
    public int $lists = 0;

    public int $localized = 0;

    public int $objects = 0;

    /**
     * The types of the values stored at a `SCALAR` node, with their count.
     *
     * @var array<string, int>
     */
    public array $types = [];

    /**
     * @param  list<string>  $segments  The path of the node.
     */
    public function __construct(
        public string $kind,
        public array $segments = [],
    ) {}

    public function path(): string
    {
        return implode('.', $this->segments);
    }
}
