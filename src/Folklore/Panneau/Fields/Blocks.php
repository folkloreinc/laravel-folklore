<?php

namespace Folklore\Panneau\Fields;

use Folklore\Contracts\Entities\Block as BlockContract;
use Folklore\Contracts\Entities\HasBlocks;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Panneau\BlockTypes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;
use Panneau\Fields\BlockType;
use Panneau\Fields\Field;
use Panneau\Fields\Media;
use Panneau\Fields\Relation;
use Panneau\Fields\Repeater;
use Panneau\Forms\RuleSet;
use Panneau\Forms\SaveContext;
use Panneau\Forms\ValidationContext;
use Panneau\Media\MediaTransform;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\MediaResource;
use Panneau\Resources\ResourceRegistry;

/**
 * The blocks of a page, or of a block: a Panneau `repeater` of typed blocks
 * (docs/decisions/0022).
 *
 * Its types are those of the `BlockTypes` registry, or those given to `types()`, narrowed with
 * `onlyTypes()` and `exceptTypes()`. A type with a `Blocks` field among its fields nests
 * blocks: `maxDepth()` limits how deep, and a type whose blocks would be deeper than the limit
 * isn't offered.
 *
 * Blocks are read from the item's `blocks()` (`HasBlocks`, or an entity with that method), from
 * Folklore's block entities or models, or from arrays. Saved blocks keep their id, so that a repository that stores them as
 * rows (`blocks_pivot`) updates them rather than creating new ones: an id is kept only if it
 * is one of the item's blocks, once.
 */
class Blocks extends Repeater
{
    /**
     * The levels of blocks when no field sets `maxDepth()`: the blocks of the field, and two
     * levels of blocks within them.
     */
    public const DEFAULT_MAX_DEPTH = 3;

    /**
     * The types given to `types()`. Null: the registry's.
     *
     * @var list<BlockType>|null
     */
    protected ?array $givenTypes = null;

    /**
     * @var list<string>|null
     */
    protected ?array $onlyTypes = null;

    /**
     * @var list<string>
     */
    protected array $exceptTypes = [];

    protected ?int $maxDepth = null;

    /**
     * The level of the field's blocks: 1 for a field of a resource, 2 for a field of one of
     * its blocks…
     */
    protected int $level = 1;

    /**
     * The `Blocks` field whose block has this field, for a nested field.
     */
    protected ?Blocks $parentField = null;

    /**
     * The ids of the item's blocks, and those kept so far, while the outermost field extracts
     * the values of a save.
     *
     * @var array{allowed: array<string, true>, kept: array<string, true>}|null
     */
    private ?array $ids = null;

    /**
     * The types of the field, instead of the registry's.
     *
     * @param  list<BlockType>  $types
     */
    public function types(array $types): static
    {
        $this->givenTypes = $types;
        $this->types = null;

        return $this;
    }

    /**
     * Only offers these types, by id.
     *
     * @param  string|list<string>  ...$types
     */
    public function onlyTypes(string|array ...$types): static
    {
        $this->onlyTypes = self::flatten($types);
        $this->types = null;

        return $this;
    }

    /**
     * Doesn't offer these types, by id.
     *
     * @param  string|list<string>  ...$types
     */
    public function exceptTypes(string|array ...$types): static
    {
        $this->exceptTypes = array_values(array_unique([...$this->exceptTypes, ...self::flatten($types)]));
        $this->types = null;

        return $this;
    }

    /**
     * The levels of blocks, counted from this field, whose blocks are the first, as in 1.x:
     * `maxDepth(1)` nests no blocks, and `maxDepth(2)` on the field of a block lets its
     * blocks have blocks of their own only if those don't nest. The smallest limit of the
     * field and of the fields it is nested in applies.
     */
    public function maxDepth(int $depth): static
    {
        if ($depth < 1) {
            throw new LogicException('The maximum depth of blocks is at least 1.');
        }
        $this->maxDepth = $depth;
        $this->types = null;

        return $this;
    }

    /**
     * The level of the field's blocks, from 1 for a field of a resource.
     */
    public function level(): int
    {
        return $this->level;
    }

    /**
     * The deepest level of blocks the field allows, counted from the outermost field: the
     * smallest of the limits that this field and those it is nested in set, or
     * `DEFAULT_MAX_DEPTH` when none does.
     */
    public function depthLimit(): int
    {
        $limits = [];
        for ($field = $this; $field !== null; $field = $field->parentField) {
            if ($field->maxDepth !== null) {
                $limits[] = $field->level + $field->maxDepth - 1;
            }
        }

        return count($limits) > 0 ? min($limits) : self::DEFAULT_MAX_DEPTH;
    }

    /**
     * The types the field offers, with their nested `Blocks` fields at the next level.
     *
     * @return list<BlockType>
     */
    public function blockTypes(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $types = [];
        foreach ($this->givenTypes ?? app(BlockTypes::class)->all() as $type) {
            $id = $type->id();
            if (($this->onlyTypes !== null && ! in_array($id, $this->onlyTypes, true)) || in_array($id, $this->exceptTypes, true)) {
                continue;
            }
            $fields = $type->getFields();
            $nests = count(array_filter($fields, fn (Field $field): bool => $field instanceof self)) > 0;
            if ($nests && $this->level + 1 > $this->depthLimit()) {
                continue;
            }
            $types[] = $nests
                ? (clone $type)->fields(array_map(fn (Field $field): Field => $field instanceof self ? $field->nestedIn($this) : $field, $fields))
                : $type;
        }

        return $this->types = $types;
    }

    /**
     * A copy of the field for the blocks of a block of another field, one level deeper.
     */
    protected function nestedIn(Blocks $parent): static
    {
        $nested = clone $this;
        $nested->parentField = $parent;
        $nested->level = $parent->level + 1;
        $nested->types = null;
        $nested->ids = null;

        return $nested;
    }

    /**
     * The blocks of an item: for a field named `blocks`, the item's `blocks()`, as a page has
     * them (`HasBlocks`), or an entity of the site with a `blocks()` method.
     */
    public function resolve(object $item): mixed
    {
        $blocks = $this->resolveUsing === null ? $this->blocksOfItem($item) : null;

        return $blocks !== null ? $this->serialize($blocks) : parent::resolve($item);
    }

    /**
     * The `blocks()` of an item, for a field named `blocks`: an entity, not a model, whose
     * `blocks()` is a relation. Null for other items.
     */
    private function blocksOfItem(object $item): mixed
    {
        if ($this->name !== 'blocks' || $item instanceof Model) {
            return null;
        }
        if ($item instanceof HasBlocks) {
            return $item->blocks();
        }

        return method_exists($item, 'blocks') ? $item->blocks() : null;
    }

    public function serialize(mixed $value): mixed
    {
        $blocks = $this->blocksOf($value);
        if ($blocks === null) {
            return $value;
        }

        $this->blockTypes();
        $serialized = parent::serialize($this->withMediaIds($blocks));
        if (! is_array($serialized)) {
            return $serialized;
        }
        $serialized = $this->withMediaReferences($this->withReferences($serialized));
        foreach ($blocks as $index => $block) {
            if (! is_array($serialized[$index] ?? null)) {
                continue;
            }
            $id = self::idOf($block['id'] ?? null);
            if ($id !== null) {
                $serialized[$index] = ['id' => $id, ...$serialized[$index]];
            }
        }

        return $serialized;
    }

    protected function typeDefinition(): array
    {
        $this->blockTypes();

        return parent::typeDefinition();
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        $this->blockTypes();
        parent::collectNestedRules($rules, $path, $value, $context);
        $rules->add($path.'.*.id', ['nullable', 'string']);
    }

    /**
     * Keeps the declared values of each block, and its id when it is one of the item's blocks
     * that no other block of the save has.
     */
    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        $this->blockTypes();
        $outermost = $this->parentField === null;
        if ($outermost) {
            $this->ids = ['allowed' => $context->item !== null ? $this->idsOfItem($context->item) : [], 'kept' => []];
        }

        try {
            $extracted = parent::extractValue($value, $context);
            if (! is_array($value) || ! is_array($extracted)) {
                return $extracted;
            }
            foreach (array_values($value) as $index => $block) {
                $id = is_array($block) ? self::idOf($block['id'] ?? null) : null;
                if ($id !== null && is_array($extracted[$index] ?? null) && $this->keep($id)) {
                    $extracted[$index] = ['id' => $id, ...$extracted[$index]];
                }
            }

            return $extracted;
        } finally {
            if ($outermost) {
                $this->ids = null;
            }
        }
    }

    protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
    {
        $this->blockTypes();

        return parent::prepareValue($value, $path, $context);
    }

    public function prepareAbsent(string $path, SaveContext $context): void
    {
        $this->blockTypes();
        parent::prepareAbsent($path, $context);
    }

    /**
     * Whether a block keeps its id: it is one of the item's blocks, and no other block of the
     * save kept it.
     */
    private function keep(string $id): bool
    {
        $outermost = $this;
        while ($outermost->parentField !== null) {
            $outermost = $outermost->parentField;
        }
        if ($outermost->ids === null || ! isset($outermost->ids['allowed'][$id]) || isset($outermost->ids['kept'][$id])) {
            return false;
        }
        $outermost->ids['kept'][$id] = true;

        return true;
    }

    /**
     * The ids of the blocks of an item, at every level.
     *
     * @return array<string, true>
     */
    private function idsOfItem(object $item): array
    {
        $value = $this->resolveUsing !== null
            ? ($this->resolveUsing)($item, $this)
            : ($this->blocksOfItem($item) ?? data_get($item, $this->name));

        return $this->idsOf($value);
    }

    /**
     * @return array<string, true>
     */
    private function idsOf(mixed $value): array
    {
        $ids = [];
        foreach ($this->blocksOf($value) ?? [] as $block) {
            $id = self::idOf($block['id'] ?? null);
            if ($id !== null) {
                $ids[$id] = true;
            }
            foreach ($this->typeOf($block)?->getFields() ?? [] as $field) {
                if ($field instanceof self) {
                    $ids += $field->idsOf($block[$field->name()] ?? null);
                }
            }
        }

        return $ids;
    }

    /**
     * Blocks as arrays: their values, with their `type` and their `id`. Null for a value that
     * isn't a list of blocks.
     *
     * @return list<array<array-key, mixed>>|null
     */
    protected function blocksOf(mixed $value): ?array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }
        if (! is_iterable($value)) {
            return null;
        }

        $blocks = [];
        foreach ($value as $block) {
            if ($block instanceof ToEntity) {
                $block = $block->toEntity();
            }
            $blocks[] = match (true) {
                $block instanceof BlockContract => [...($block->data() ?? []), 'id' => $block->id(), 'type' => $block->type()],
                $block instanceof Arrayable => $block->toArray(),
                is_array($block) => $block,
                default => [],
            };
        }

        return $blocks;
    }

    /**
     * @param  array<array-key, mixed>  $block
     */
    private function typeOf(array $block): ?BlockType
    {
        foreach ($this->blockTypes() as $type) {
            if ($type->id() === ($block['type'] ?? null)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Turns the values of the relation fields of the blocks into references
     * (`{ "id", "label" }`), as Panneau does for the relations of an item: the related items,
     * and the ids that blocks stored as is, found within the related resource's scope, with
     * one query per related resource for the whole list (folkloreinc/laravel-panneau#117).
     *
     * @param  array<array-key, mixed>  $blocks
     * @return array<array-key, mixed>
     */
    private function withReferences(array $blocks): array
    {
        $resources = app(ResourceRegistry::class);
        $serializer = app(ItemSerializer::class);

        // The relation fields of each block, and the ids to find per related resource
        $relations = [];
        $ids = [];
        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }
            foreach ($this->typeOf($block)?->getFields() ?? [] as $field) {
                if (! $field instanceof Relation || ! isset($block[$field->name()])) {
                    continue;
                }
                $related = $field->relatedResource() !== null ? $resources->resolve($field->relatedResource()) : null;
                $relations[$index][$field->name()] = $related;
                $value = $block[$field->name()];
                foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $item) {
                    $id = self::idOf($item);
                    if ($id !== null && $related !== null) {
                        $ids[$related->id()][$id] = true;
                    }
                }
            }
        }

        $found = [];
        foreach ($ids as $resource => $resourceIds) {
            $related = $resources->find($resource);
            if ($related === null) {
                continue;
            }
            $repository = $resources->repository($related);
            $items = $repository->query(new ResourceQuery(
                perPage: count($resourceIds),
                scope: $related->repositoryScope(request()),
                ids: array_map(strval(...), array_keys($resourceIds)),
            ));
            foreach ($items->items() as $item) {
                $found[$resource][$repository->id($item)] = $item;
            }
        }

        foreach ($relations as $index => $fields) {
            $block = $blocks[$index];
            if (! is_array($block)) {
                continue;
            }
            foreach ($fields as $name => $related) {
                $reference = function (mixed $item) use ($found, $related, $serializer): mixed {
                    $id = self::idOf($item);
                    if ($id !== null && $related !== null) {
                        $item = $found[$related->id()][$id] ?? $item;
                    }

                    return is_object($item) ? $serializer->reference($related, $item) : $item;
                };
                $value = $block[$name];
                $block[$name] = is_array($value) && array_is_list($value) ? array_map($reference, $value) : $reference($value);
            }
            $blocks[$index] = $block;
        }

        return $blocks;
    }

    /**
     * Turns the values of the media fields of the blocks (`Media`, `Image`) into media
     * references (`{ "id", "resource", "label", "media", "transform" }`), as Panneau does for the
     * media fields of an item: the media items, and the `{ "id", "resource", "transform" }` that
     * blocks stored, or that the page's cast resolved with their edits (`withMediaIds()`), found
     * within their library's scope, with one query per library for the whole list. A value keeps
     * its `transform`, the edits of the image for this use. A media that isn't in its library
     * anymore keeps its id, library and transform.
     *
     * @param  array<array-key, mixed>  $blocks
     * @return array<array-key, mixed>
     */
    private function withMediaReferences(array $blocks): array
    {
        $resources = app(ResourceRegistry::class);

        // The media fields of each block, with their libraries, and the ids to find per library
        $fields = [];
        $ids = [];
        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }
            foreach ($this->typeOf($block)?->getFields() ?? [] as $field) {
                if (! $field instanceof Media || ! isset($block[$field->name()])) {
                    continue;
                }
                $libraries = self::librariesOf($resources, $field);
                $fields[$index][$field->name()] = [$field, $libraries];
                foreach (self::mediaEntries($field, $block[$field->name()]) as $entry) {
                    $id = is_array($entry) ? Media::idOf($entry) : null;
                    $library = is_array($entry) ? self::libraryOf($libraries, $entry) : null;
                    if ($id !== null && $library !== null) {
                        $ids[$library->id()][$id] = true;
                    }
                }
            }
        }

        $found = [];
        foreach ($ids as $libraryId => $libraryIds) {
            $library = $resources->find($libraryId);
            if (! $library instanceof MediaResource) {
                continue;
            }
            $repository = $resources->repository($library);
            $items = $repository->query(new ResourceQuery(
                perPage: count($libraryIds),
                scope: $library->repositoryScope(request()),
                ids: array_map(strval(...), array_keys($libraryIds)),
            ));
            foreach ($items->items() as $item) {
                $found[$libraryId][$repository->id($item)] = $item;
            }
        }

        foreach ($fields as $index => $blockFields) {
            $block = $blocks[$index];
            if (! is_array($block)) {
                continue;
            }
            foreach ($blockFields as $name => [$field, $libraries]) {
                $reference = function (mixed $entry) use ($libraries, $found, $resources): mixed {
                    if (is_object($entry)) {
                        $library = self::libraryOfItem($libraries, $entry);
                        if ($library !== null) {
                            return self::mediaReference($resources, $library, $entry);
                        }
                        // Not a media of the field's libraries: its id only, never the object
                        $id = Media::idOf(data_get($entry, 'id'));

                        return $id !== null ? ['id' => $id] : null;
                    }
                    $id = is_array($entry) ? Media::idOf($entry) : null;
                    $library = is_array($entry) ? self::libraryOf($libraries, $entry) : null;
                    $item = $id !== null && $library !== null ? ($found[$library->id()][$id] ?? null) : null;
                    if (! is_array($entry) || $library === null || $item === null) {
                        return $entry;
                    }
                    $media = self::mediaReference($resources, $library, $item);
                    if (isset($entry['transform'])) {
                        $media['transform'] = $entry['transform'];
                    }

                    return $media;
                };
                $convert = fn (mixed $value): mixed => $field->isMultiple()
                    ? (is_array($value) ? array_values(array_map($reference, $value)) : [])
                    : $reference($value);
                $value = $block[$name];
                $block[$name] = self::isLocalizedMedia($field, $value) && is_array($value)
                    ? array_map($convert, $value)
                    : $convert($value);
            }
            $blocks[$index] = $block;
        }

        return $blocks;
    }

    /**
     * Turns the media that the page's cast resolved with the edits they keep
     * (`{ "id": <media>, "transform" }`, `Json::media(transform: true)`) into the values Panneau
     * reads (`{ "id", "resource", "transform" }`), so that they keep their transform: Panneau
     * serializes a media item without one. A media that is gone (`{ "id": null }`) is null.
     *
     * @param  list<array<array-key, mixed>>  $blocks
     * @return list<array<array-key, mixed>>
     */
    private function withMediaIds(array $blocks): array
    {
        $resources = app(ResourceRegistry::class);
        foreach ($blocks as $index => $block) {
            foreach ($this->typeOf($block)?->getFields() ?? [] as $field) {
                if (! $field instanceof Media || ! isset($block[$field->name()])) {
                    continue;
                }
                $libraries = null;
                $entry = function (mixed $entry) use ($field, $resources, &$libraries): mixed {
                    if (! is_array($entry) || ! array_key_exists('id', $entry) || ! (is_object($entry['id']) || $entry['id'] === null)) {
                        return $entry;
                    }
                    if ($entry['id'] === null) {
                        return null;
                    }
                    $libraries ??= self::librariesOf($resources, $field);
                    $library = self::libraryOfItem($libraries, $entry['id']);
                    $value = $library !== null
                        ? ['id' => $resources->repository($library)->id($entry['id']), 'resource' => $library->id()]
                        : ['id' => Media::idOf(data_get($entry['id'], 'id'))];
                    $transform = MediaTransform::normalize($entry['transform'] ?? null);
                    if ($transform !== null) {
                        $value['transform'] = $transform;
                    }

                    return $value;
                };
                $convert = fn (mixed $value): mixed => $field->isMultiple() && is_array($value) && array_is_list($value)
                    ? array_map($entry, $value)
                    : $entry($value);
                $value = $block[$field->name()];
                $blocks[$index][$field->name()] = self::isLocalizedMedia($field, $value) && is_array($value)
                    ? array_map($convert, $value)
                    : $convert($value);
            }
        }

        return $blocks;
    }

    /**
     * The libraries of a media field, by id.
     *
     * @return array<string, MediaResource>
     */
    private static function librariesOf(ResourceRegistry $resources, Media $field): array
    {
        $libraries = [];
        foreach ($resources->mediaLibraries($field->libraries()) as $library) {
            $libraries[$library->id()] = $library;
        }

        return $libraries;
    }

    /**
     * A reference to a media item of a library, as Panneau gives it for the media fields of an
     * item: its id, its library, its label and its `MediaInfo`.
     *
     * @return array{id: string, resource: string, label?: string, media: array<string, mixed>}
     */
    private static function mediaReference(ResourceRegistry $resources, MediaResource $library, object $item): array
    {
        $reference = ['id' => $resources->repository($library)->id($item), 'resource' => $library->id()];
        $label = $library->mediaLabel($item);
        if ($label !== null) {
            $reference['label'] = $label;
        }
        $reference['media'] = $library->media($item);

        return $reference;
    }

    /**
     * The entries of the serialized value of a media field: one, a list, or those of every
     * locale.
     *
     * @return list<mixed>
     */
    private static function mediaEntries(Media $field, mixed $value): array
    {
        $values = self::isLocalizedMedia($field, $value) && is_array($value) ? array_values($value) : [$value];
        $entries = [];
        foreach ($values as $localized) {
            if ($field->isMultiple()) {
                array_push($entries, ...(is_array($localized) ? array_values($localized) : []));
            } else {
                $entries[] = $localized;
            }
        }

        return $entries;
    }

    /**
     * Whether the value of a media field is keyed by locale.
     */
    private static function isLocalizedMedia(Media $field, mixed $value): bool
    {
        return $field->isLocalized() && is_array($value) && ! array_is_list($value) && ! array_key_exists('id', $value);
    }

    /**
     * The library of a stored value: the `resource` it keeps, among the libraries, or the only
     * one.
     *
     * @param  array<string, MediaResource>  $libraries
     * @param  array<array-key, mixed>  $entry
     */
    private static function libraryOf(array $libraries, array $entry): ?MediaResource
    {
        $named = $entry['resource'] ?? null;
        if (is_string($named)) {
            return $libraries[$named] ?? null;
        }

        return count($libraries) === 1 ? reset($libraries) : null;
    }

    /**
     * The library of a media item, such as a model that a block's data resolved: the first of
     * the libraries whose items are of its class, or the only one.
     *
     * @param  array<string, MediaResource>  $libraries
     */
    private static function libraryOfItem(array $libraries, object $item): ?MediaResource
    {
        foreach ($libraries as $library) {
            $class = $library->itemClass();
            if ($class !== null && $item instanceof $class) {
                return $library;
            }
        }

        return count($libraries) === 1 ? reset($libraries) : null;
    }

    private static function idOf(mixed $value): ?string
    {
        if (is_int($value) || (is_string($value) && $value !== '')) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param  array<array-key, string|list<string>>  $types
     * @return list<string>
     */
    private static function flatten(array $types): array
    {
        $ids = [];
        foreach ($types as $type) {
            foreach ((array) $type as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
