<?php

namespace Folklore\Upgrade;

use Folklore\Contracts\Eloquent\HasJsonDataColumnExtract;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Eloquent\JsonDataCast as LegacyJsonDataCast;
use Folklore\JsonData\Contracts\HasJsonDataSchema;
use Folklore\JsonData\Contracts\LocaleResolver;
use Folklore\JsonData\JsonData;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\JsonData\Schema\ReferenceType;
use Folklore\Upgrade\Checks\StoredReferencesCheck;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Converts the 1.x JSON declarations of a site's models, `getJsonDataRelations()` and
 * `getJsonDataColumnExtract()`, to `jsonDataSchema()` (docs/upgrade.md, "JSON data"):
 * a schema that compiles into the same declarations, so that the cast reads, writes and
 * syncs the same data. Its root is `additional(true)`, so the keys the declarations don't
 * name are kept.
 *
 * A `*` segment is a list, a map or the locales of a translatable node, which the 1.x
 * declarations don't say: the stored data decides. The three compile into the same path,
 * so the choice changes what `JsonData::fill()` and `Json::rules()` do, not what the cast
 * does. Where the stored data doesn't decide, or the paths aren't one tree (`cards.*` and
 * `cards.fr.*`), the references are declared with `paths()`, as the 1.x paths are written.
 *
 * A relation whose key isn't mapped is `Json::relation()`, which writes and resolves it as
 * a 1.x declaration does (docs/decisions/0016), and `delete` is `deletesUnreferenced()`.
 * Declarations that a schema can't give the same behaviour are left as they are, with a
 * `// TODO(folklore-3)` that says why: a method that reads the model or the attribute, the
 * options without a node (`skip`, `get`, `set`, `before_set`, callables, `delete` on a
 * relation that doesn't own its rows), and the macros of a relation.
 */
class JsonSchemaConverter
{
    /**
     * The 1.x methods, and the interface that declares each one.
     *
     * @var array<string, class-string>
     */
    public const METHODS = [
        'getJsonDataRelations' => HasJsonDataRelations::class,
        'getJsonDataColumnExtract' => HasJsonDataColumnExtract::class,
    ];

    /**
     * The options of a 1.x declaration that a schema can give: `relation` and `key` are its
     * reference node, `sync => false` a reference without a relation, `delete` is
     * `deletesUnreferenced()`, and `lazy` is what `JsonData::load()` and
     * `withJsonReferences()` do.
     *
     * @var list<string>
     */
    public const OPTIONS = ['relation', 'key', 'sync', 'lazy', 'delete', 'path'];

    /**
     * The macros of `Folklore\Eloquent\JsonDataCast`.
     *
     * @var list<string>
     */
    public const MACROS = ['get', 'set', 'before_set'];

    /**
     * @var list<string>|null
     */
    protected ?array $locales = null;

    /**
     * The conversions of the site's models that declare their JSON data with the 1.x
     * declarations, one per file.
     *
     * @return list<JsonSchemaConversion>
     */
    public function conversions(Site $site): array
    {
        $conversions = [];
        foreach ($site->models() as $class) {
            $conversion = $this->convert($site, $class);
            if (! is_null($conversion) && ! isset($conversions[$conversion->absolutePath])) {
                $conversions[$conversion->absolutePath] = $conversion;
            }
        }

        return array_values($conversions);
    }

    /**
     * The conversion of a model, or null when it declares nothing with the 1.x methods, or
     * isn't the site's.
     *
     * @param  class-string  $class
     */
    public function convert(Site $site, string $class): ?JsonSchemaConversion
    {
        $reflection = new ReflectionClass($class);
        $methods = [];
        $declaredIn = null;
        foreach (array_keys(self::METHODS) as $name) {
            if (! $reflection->hasMethod($name)) {
                continue;
            }
            $method = $reflection->getMethod($name);
            $file = $method->getFileName();
            if (! is_string($file) || ! $this->isSiteFile($site, $file)) {
                continue;
            }
            $methods[] = $name;
            if ($file !== $reflection->getFileName()) {
                $declaredIn = $declaredIn ?? [$file, $method->getDeclaringClass()->getName()];
            }
        }
        if (count($methods) === 0) {
            return null;
        }

        $path = $declaredIn[0] ?? (string) $reflection->getFileName();
        $code = (string) file_get_contents($path);
        $file = new ModelFile($code);
        // The file decides: a class loaded before its file was converted still has the methods
        $methods = array_values(array_filter($methods, fn (string $name) => ! is_null($file->method($name))));
        if (count($methods) === 0) {
            return null;
        }
        $line = $this->line($code, $file->method($methods[0])['function'] ?? 0);

        $blockers = [];
        if (! is_null($declaredIn)) {
            $blockers[] = "`{$class}` gets its declarations from `{$declaredIn[1]}`, which other models may use: convert them in each model";
        }
        // A method that builds its array the same way for every model and attribute (a loop
        // over the locales) gives the schema of what it returns, with a TODO to check it
        $built = [];
        foreach ($methods as $name) {
            $method = $file->method($name);
            if ($method['literal'] ?? false) {
                continue;
            }
            if ($method['static'] ?? false) {
                $built[] = $name;
            } else {
                $blockers[] = "`{$name}()` builds its array from the model or the attribute: write the schema by hand";
            }
        }

        $todos = [];
        $schemas = [];
        if (count($blockers) === 0) {
            /** @var Model $model */
            $model = new $class;
            $attributes = array_keys(array_filter((new StoredReferencesCheck)->jsonAttributes($model)));
            if (count($attributes) === 0) {
                $blockers[] = 'no attribute is cast with `JsonDataCast`';
            }
            foreach ($attributes as $attribute) {
                $root = $this->tree($model, (string) $attribute, $blockers);
                if (count($blockers) > 0) {
                    break;
                }
                $this->observe($model, (string) $attribute, $root, $site->limit);
                $schemas[(string) $attribute] = $root;
            }
        }

        if (count($blockers) > 0) {
            $blockers = array_values(array_unique($blockers));
            $file->commentMethod($methods[0], 'not converted to jsonDataSchema() by folklore:upgrade --schemas: '.implode('; ', $blockers).'.');

            return new JsonSchemaConversion($class, $site->relative($path), $path, $line, $methods, $file->code(), $blockers);
        }

        // Declarations that declare nothing are removed: an empty schema would only change
        // how the model's repository fills it
        if (count(array_filter($schemas, fn (SchemaNode $root) => count($root->properties) > 0)) === 0) {
            foreach ($methods as $name) {
                $file->removeMethod($name);
            }
            $file->replaceInterfaces(array_values(self::METHODS));
            foreach (self::METHODS as $interface) {
                $file->removeUnusedImport($interface);
            }

            return new JsonSchemaConversion($class, $site->relative($path), $path, $line, $methods, $file->code(), empty: true);
        }

        $names = [
            'json' => $file->import(Json::class),
            'object' => $file->import(ObjectType::class),
            'contract' => $file->import(HasJsonDataSchema::class),
        ];
        foreach ($built as $name) {
            $todos[] = "`{$name}()` built its array in 1.x: this schema is what it returned with the site's config".(count($this->locales()) > 0 ? ' (locales: '.implode(', ', $this->locales()).')' : '').'; check that nothing else in it depends on the environment.';
        }
        $indent = $file->method($methods[0])['indent'] ?? '    ';
        $file->replaceMethod($methods[0], $this->method($schemas, $names, $indent, $todos));
        foreach (array_slice($methods, 1) as $name) {
            $file->removeMethod($name);
        }
        $file->replaceInterfaces(array_values(self::METHODS), $names['contract'], HasJsonDataSchema::class);
        foreach (self::METHODS as $interface) {
            $file->removeUnusedImport($interface);
        }

        return new JsonSchemaConversion($class, $site->relative($path), $path, $line, $methods, $file->code(), [], $todos);
    }

    /**
     * Writes the file of a conversion.
     */
    public function write(JsonSchemaConversion $conversion): void
    {
        if (file_put_contents($conversion->absolutePath, $conversion->code) === false) {
            throw new RuntimeException("{$conversion->path} could not be written.");
        }
    }

    /**
     * The tree of the declarations of an attribute. A property of the root whose paths
     * aren't one tree (`cards.*` and `cards.fr.*`) declares them with `paths()`.
     *
     * @param  list<string>  $blockers
     */
    protected function tree(Model $model, string $attribute, array &$blockers): SchemaNode
    {
        $root = new SchemaNode(SchemaNode::OBJECT);
        /** @var array<string, string> $references */
        $references = [];
        /** @var array<string, string> $conflicts */
        $conflicts = [];

        $relations = $model instanceof HasJsonDataRelations ? $model->getJsonDataRelations($attribute, null, []) : [];
        $relations = $relations instanceof Collection ? $relations->all() : (is_array($relations) ? $relations : []);
        foreach ($relations as $path => $options) {
            $path = (string) $path;
            $reference = $this->reference($model, $path, $options, $blockers);
            if (! is_null($reference) && $this->isPath($path, $blockers)) {
                $references[$path] = $reference;
                $this->insert($root, $path, $reference, null, $blockers, $conflicts);
            }
        }

        $columns = $model instanceof HasJsonDataColumnExtract ? $model->getJsonDataColumnExtract($attribute, null, []) : [];
        foreach (is_array($columns) ? $columns : [] as $path => $column) {
            if (! is_string($column)) {
                continue;
            }
            if (str_contains((string) $path, '*')) {
                $blockers[] = "the column `{$column}` is extracted from `{$path}`, a path with `*`";

                continue;
            }
            if ($this->isPath((string) $path, $blockers)) {
                $this->insert($root, (string) $path, null, $column, $blockers, $conflicts);
            }
        }

        foreach ($conflicts as $property => $conflict) {
            $node = new SchemaNode(SchemaNode::PATHS, [$property]);
            foreach ($references as $path => $reference) {
                if (str_starts_with($path, $property.'.')) {
                    $node->paths[substr($path, strlen($property) + 1)] = $reference;
                }
            }
            // A reference or a column at the property itself has no place among paths
            $own = $root->properties[$property] ?? null;
            if (isset($references[$property]) || ! is_null($own?->column) || $this->hasColumns($own)) {
                $blockers[] = $conflict;

                continue;
            }
            $root->properties[$property] = $node;
        }

        return $root;
    }

    /**
     * Whether a declared path has segments that a schema takes: property names and `*`,
     * not first.
     *
     * @param  list<string>  $blockers
     */
    protected function isPath(string $path, array &$blockers): bool
    {
        foreach (explode('.', $path) as $index => $segment) {
            $blocker = match (true) {
                $segment === '' => "`{$path}` has an empty segment",
                $segment === '**' => "`{$path}` has `**`: declare it as a `Json::document()` by hand",
                $segment !== '*' && str_contains($segment, '*') => "`{$path}` has the segment `{$segment}`, which matches only that key (a typo of `*.`?)",
                $index === 0 && $segment === '*' => "`{$path}` starts with `*`: the root of a schema is an object",
                default => null,
            };
            if (! is_null($blocker)) {
                $blockers[] = $blocker;

                return false;
            }
        }

        return true;
    }

    protected function hasColumns(?SchemaNode $node): bool
    {
        if (is_null($node)) {
            return false;
        }
        if (! is_null($node->column)) {
            return true;
        }
        foreach ([...$node->properties, ...(is_null($node->items) ? [] : [$node->items])] as $child) {
            if ($this->hasColumns($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The code of the reference node of a declaration, or null with why when it has none.
     *
     * @param  list<string>  $blockers
     */
    protected function reference(Model $model, string $path, mixed $options, array &$blockers): ?string
    {
        $options = is_string($options) ? ['relation' => $options] : $options;
        if (! is_array($options) || ! is_string($options['relation'] ?? null)) {
            $blockers[] = is_array($options) && is_callable($options['relation'] ?? null)
                ? "`{$path}` picks its relation with a callable"
                : "`{$path}` isn't declared with a relation";

            return null;
        }
        $relationName = $options['relation'];

        $unsupported = array_diff(array_keys($options), self::OPTIONS);
        $delete = $options['delete'] ?? false;
        if (! is_bool($delete)) {
            $unsupported[] = 'delete';
        }
        if (isset($options['path']) && $options['path'] !== $path) {
            $unsupported[] = 'path';
        }
        $sync = $options['sync'] ?? true;
        if (! is_bool($sync)) {
            $unsupported[] = 'sync';
        }
        if (count($unsupported) > 0) {
            $blockers[] = '`'.$path.'` has '.implode(', ', array_map(fn ($option) => "`{$option}`", array_unique($unsupported))).', which a schema node doesn\'t have';

            return null;
        }

        foreach (self::MACROS as $macro) {
            if (LegacyJsonDataCast::hasMacro($model::class.':'.$relationName.':'.$macro) || LegacyJsonDataCast::hasMacro($relationName.':'.$macro)) {
                $blockers[] = "the relation `{$relationName}` has a `{$macro}` macro of `JsonDataCast`: move it to a handler first";

                return null;
            }
        }

        try {
            $relation = method_exists($model, $relationName) ? $model->{$relationName}() : null;
        } catch (Throwable) {
            $relation = null;
        }
        if (! $relation instanceof Relation) {
            $blockers[] = "`{$path}` refers to `{$relationName}`, which isn't a relation of the model";

            return null;
        }
        $related = $relation->getRelated()::class;
        if ($delete && ! $relation instanceof HasOneOrMany) {
            $blockers[] = "`{$path}` has `delete` on `{$relationName}`, which doesn't own its rows: it would delete the related models themselves, and laravel-json-data refuses it";

            return null;
        }

        $key = $options['key'] ?? null;
        if (is_string($key)) {
            $target = JsonData::target($key);
            if (is_null($target) || ! $target->accepts($related)) {
                $blockers[] = "`{$path}` writes the key `{$key}`, which isn't mapped to `{$related}`";

                return null;
            }
        } else {
            $key = JsonData::keyFor($related);
        }
        // A path that isn't synced deletes nothing, in 1.x too
        $deletes = $delete && $sync ? '->deletesUnreferenced()' : '';
        // A key that isn't mapped: written and resolved as by the 1.x declaration
        if (is_null($key)) {
            if (! $sync) {
                $blockers[] = "`{$path}` has `sync => false` on `{$relationName}://`, which isn't mapped: a reference that isn't synced needs a mapped key";

                return null;
            }

            return '{json}::relation('.$this->export($relationName).')'.$deletes;
        }

        // The medias module's `Json::media()`, when it is on
        $media = Json::hasMacro('media') ? Json::__callStatic('media', []) : null;
        if ($sync && $relationName === 'medias' && $media instanceof ReferenceType && $media->getKey() === $key && $media->getRelation() === 'medias') {
            return '{json}::media()'.$deletes;
        }

        return '{json}::reference('.$this->export($key).')'.($sync ? '->relation('.$this->export($relationName).')' : '').$deletes;
    }

    /**
     * Puts a reference or an extracted column at its path. A path that doesn't fit the
     * tree is recorded in `$conflicts`, by the property of the root it is under.
     *
     * @param  list<string>  $blockers
     * @param  array<string, string>  $conflicts
     */
    protected function insert(SchemaNode $root, string $path, ?string $reference, ?string $column, array &$blockers, array &$conflicts): void
    {
        $segments = explode('.', $path);
        $conflict = function (string $why) use ($segments, &$conflicts): void {
            $conflicts[$segments[0]] ??= $why;
        };

        $node = $root;
        foreach ($segments as $index => $segment) {
            $last = $index === count($segments) - 1;
            $kind = $last ? ($reference !== null ? SchemaNode::REFERENCE : SchemaNode::SCALAR) : ($segments[$index + 1] === '*' ? SchemaNode::ITEMS : SchemaNode::OBJECT);
            $sub = array_slice($segments, 0, $index + 1);

            if ($segment === '*') {
                if ($node->kind !== SchemaNode::ITEMS) {
                    $conflict("`{$path}` is under a path declared otherwise");

                    return;
                }
                $child = $node->items ??= new SchemaNode($kind, $sub);
            } else {
                if ($node->kind !== SchemaNode::OBJECT) {
                    $conflict("`{$path}` is under a path declared otherwise");

                    return;
                }
                $child = $node->properties[$segment] ??= new SchemaNode($kind, $sub);
            }

            if ($last) {
                if ($kind === SchemaNode::REFERENCE && $child->kind === SchemaNode::SCALAR && is_null($child->reference)) {
                    // Extracted, then declared as a reference
                    $child->kind = SchemaNode::REFERENCE;
                }
                if ($child->kind === SchemaNode::REFERENCE && $kind === SchemaNode::REFERENCE && ! is_null($child->reference) && $child->reference !== $reference) {
                    $blockers[] = "`{$path}` is declared twice";

                    return;
                }
                if ($child->kind !== SchemaNode::REFERENCE && $child->kind !== SchemaNode::SCALAR) {
                    $conflict("`{$path}` is a reference or a column, and the path of other declarations");

                    return;
                }
                $child->reference ??= $reference;
                $child->column ??= $column;

                return;
            }
            if ($child->kind !== $kind) {
                $conflict("`{$path}` is under a path declared otherwise");

                return;
            }
            $node = $child;
        }
    }

    /**
     * Reads the stored values of an attribute into the tree: the shape of the arrays at
     * each `*`, and the types of the extracted values.
     */
    protected function observe(Model $model, string $attribute, SchemaNode $root, int $limit): void
    {
        try {
            foreach (StoredReferencesCheck::storedValues($model, $attribute, $limit) as $value) {
                $this->walk($root, $value);
            }
        } catch (Throwable) {
            // A table that can't be read decides nothing: every `*` gets its TODO
        }
    }

    protected function walk(SchemaNode $node, mixed $value): void
    {
        if (is_null($value)) {
            return;
        }
        switch ($node->kind) {
            case SchemaNode::OBJECT:
                if (is_array($value)) {
                    foreach ($node->properties as $name => $property) {
                        if (array_key_exists($name, $value)) {
                            $this->walk($property, $value[$name]);
                        }
                    }
                }
                break;
            case SchemaNode::ITEMS:
                if (! is_array($value) || count($value) === 0) {
                    break;
                }
                $locales = $this->locales();
                $keys = array_map('strval', array_keys($value));
                if (array_is_list($value)) {
                    $node->lists++;
                } elseif (count($locales) > 0 && count(array_diff($keys, $locales)) === 0) {
                    $node->localized++;
                } else {
                    $node->objects++;
                }
                if (! is_null($node->items)) {
                    foreach ($value as $item) {
                        $this->walk($node->items, $item);
                    }
                }
                break;
            case SchemaNode::SCALAR:
            case SchemaNode::REFERENCE:
                $type = match (true) {
                    is_string($value) => 'string',
                    is_int($value) => 'integer',
                    is_float($value) => 'number',
                    is_bool($value) => 'boolean',
                    default => 'array',
                };
                $node->types[$type] = ($node->types[$type] ?? 0) + 1;
                break;
        }
    }

    /**
     * The code of `jsonDataSchema()`.
     *
     * @param  array<string, SchemaNode>  $schemas
     * @param  array{json: string, object: string, contract: string}  $names
     * @param  list<string>  $todos
     */
    protected function method(array $schemas, array $names, string $indent, array &$todos): string
    {
        $unit = str_contains($indent, "\t") ? "\t" : '    ';
        $lines = [
            "public function jsonDataSchema(string \$attribute): ?{$names['object']}",
            $indent.'{',
        ];
        // The TODOs of the whole method, given before those of its nodes
        foreach ($todos as $todo) {
            $lines[] = $indent.$unit.'// TODO(folklore-3): '.$todo;
        }
        $lines[] = $indent.$unit.'return match ($attribute) {';
        foreach ($schemas as $attribute => $root) {
            $code = $this->object($root, $names, $indent.$unit.$unit, $unit, $todos);
            $lines[] = $indent.$unit.$unit.$this->export($attribute).' => '.$code.'->additional(true),';
        }
        $lines[] = $indent.$unit.$unit.'default => null,';
        $lines[] = $indent.$unit.'};';
        $lines[] = $indent.'}';

        return implode("\n", $lines);
    }

    /**
     * @param  array{json: string, object: string, contract: string}  $names
     * @param  list<string>  $todos
     */
    protected function object(SchemaNode $node, array $names, string $indent, string $unit, array &$todos): string
    {
        if (count($node->properties) === 0) {
            return $names['json'].'::object([])';
        }
        $lines = [$names['json'].'::object(['];
        foreach ($node->properties as $name => $property) {
            $comments = [];
            $code = $this->node($property, $names, $indent.$unit, $unit, $comments);
            foreach ($comments as $comment) {
                $todos[] = $comment;
                $lines[] = $indent.$unit.'// TODO(folklore-3): '.$comment;
            }
            $lines[] = $indent.$unit.$this->export((string) $name).' => '.$code.',';
        }
        $lines[] = $indent.'])';

        return implode("\n", $lines);
    }

    /**
     * @param  array{json: string, object: string, contract: string}  $names
     * @param  list<string>  $comments  The TODO of the node and of its children.
     */
    protected function node(SchemaNode $node, array $names, string $indent, string $unit, array &$comments): string
    {
        switch ($node->kind) {
            case SchemaNode::OBJECT:
                $translatable = $this->translatable($node, $names, $comments);
                if (! is_null($translatable)) {
                    return $translatable;
                }
                $todos = [];
                $code = $this->object($node, $names, $indent, $unit, $todos);
                array_push($comments, ...$todos);

                return $code;
            case SchemaNode::ITEMS:
                $items = $node->items ?? new SchemaNode(SchemaNode::SCALAR);
                $observed = $node->lists + $node->localized + $node->objects;
                if ($items->kind === SchemaNode::REFERENCE && $observed > 0 && $node->localized === $observed) {
                    return $this->node($items, $names, $indent, $unit, $comments).'->translatable()';
                }
                if ($observed > 0 && $node->lists === $observed) {
                    return $names['json'].'::list('.$this->node($items, $names, $indent, $unit, $comments).')';
                }
                if ($observed > 0 && $node->lists === 0) {
                    return $names['json'].'::map('.$this->node($items, $names, $indent, $unit, $comments).')';
                }
                // No stored row says, or rows disagree: the references at their 1.x paths
                $paths = new SchemaNode(SchemaNode::PATHS, $node->segments);
                $this->collectPaths($items, count($node->segments), $paths->paths);

                return $this->node($paths, $names, $indent, $unit, $comments);
            case SchemaNode::PATHS:
                $lines = [$names['json'].'::any()->paths(['];
                foreach ($node->paths as $path => $reference) {
                    $lines[] = $indent.$unit.$this->export((string) $path).' => '.str_replace('{json}', $names['json'], $reference).',';
                }
                $lines[] = $indent.'])';

                return implode("\n", $lines);
            case SchemaNode::REFERENCE:
                $code = str_replace('{json}', $names['json'], (string) $node->reference);

                return $code.(is_null($node->column) ? '' : '->extract('.$this->export($node->column).')');
            default:
                return $this->scalar($node, $names, $comments).(is_null($node->column) ? '' : '->extract('.$this->export($node->column).')');
        }
    }

    /**
     * The references under a node, by their path from the node at `$depth` segments.
     *
     * @param  array<string, string>  $paths
     */
    protected function collectPaths(SchemaNode $node, int $depth, array &$paths): void
    {
        if ($node->kind === SchemaNode::REFERENCE && ! is_null($node->reference)) {
            $paths[implode('.', array_slice($node->segments, $depth))] = $node->reference;
        }
        foreach ([...$node->properties, ...(is_null($node->items) ? [] : [$node->items])] as $child) {
            $this->collectPaths($child, $depth, $paths);
        }
    }

    /**
     * A translatable node, for an object whose properties are the site's locales, each
     * extracted into a column named after its locale (`title.fr` → `title_fr`).
     *
     * @param  array{json: string, object: string, contract: string}  $names
     * @param  list<string>  $comments
     */
    protected function translatable(SchemaNode $node, array $names, array &$comments): ?string
    {
        $locales = $this->locales();
        $keys = array_map('strval', array_keys($node->properties));
        if (count($locales) === 0 || count(array_diff($keys, $locales)) > 0 || count(array_diff($locales, $keys)) > 0) {
            return null;
        }

        $pattern = null;
        $types = [];
        foreach ($node->properties as $locale => $property) {
            if ($property->kind !== SchemaNode::SCALAR || is_null($property->column)) {
                return null;
            }
            $position = strrpos($property->column, (string) $locale);
            if ($position === false) {
                return null;
            }
            $candidate = substr_replace($property->column, '{locale}', $position, strlen((string) $locale));
            if (! is_null($pattern) && $pattern !== $candidate) {
                return null;
            }
            $pattern = $candidate;
            foreach ($property->types as $type => $count) {
                $types[$type] = ($types[$type] ?? 0) + $count;
            }
        }

        $merged = new SchemaNode(SchemaNode::SCALAR, $node->segments);
        $merged->types = $types;

        return $this->scalar($merged, $names, $comments).'->translatable()->extract('.$this->export((string) $pattern).')';
    }

    /**
     * The node of an extracted value, by the type of its stored values.
     *
     * @param  array{json: string, object: string, contract: string}  $names
     * @param  list<string>  $comments
     */
    protected function scalar(SchemaNode $node, array $names, array &$comments): string
    {
        $types = array_keys($node->types);
        sort($types);
        $type = match ($types) {
            ['string'] => 'string',
            ['integer'] => 'integer',
            ['integer', 'number'], ['number'] => 'number',
            ['boolean'] => 'boolean',
            default => null,
        };
        if (is_null($type)) {
            $comments[] = count($types) === 0
                ? "no stored row has `{$node->path()}`: check its type."
                : "`{$node->path()}` is stored as ".implode(' and ', $types).': check its type.';
            $type = 'string';
        }

        return $names['json'].'::'.$type.'()';
    }

    /**
     * The site's locales, which a `*` of a translatable node holds.
     *
     * @return list<string>
     */
    protected function locales(): array
    {
        if (is_null($this->locales)) {
            try {
                $resolver = Container::getInstance()->make(LocaleResolver::class);
                $this->locales = array_map('strval', $resolver->locales());
            } catch (Throwable) {
                $this->locales = [];
            }
        }

        return $this->locales;
    }

    protected function isSiteFile(Site $site, string $file): bool
    {
        $base = rtrim($site->basePath, '/').'/';

        return str_starts_with($file, $base) && ! str_starts_with($file, $base.'vendor/');
    }

    protected function line(string $code, int $offset): int
    {
        return substr_count(substr($code, 0, $offset), "\n") + 1;
    }

    protected function export(string $value): string
    {
        return var_export($value, true);
    }
}
