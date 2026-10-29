<?php

namespace Folklore\Upgrade\Checks;

use Folklore\Eloquent\JsonDataCast as LegacyJsonDataCast;
use Folklore\JsonData\JsonData;
use Folklore\JsonData\JsonDataCast;
use Folklore\Upgrade\Finding;
use Folklore\Upgrade\Site;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * The `scheme://id` references stored in the JSON attributes of the site's models, read
 * from the database: their paths and keys, whether 2.0 maps their keys, and whether
 * their identifiers match the model and column that the key maps to. A site that wrote
 * `media://12`, by id, sees here that 2.0 reads `media://` by uid.
 */
class StoredReferencesCheck implements Check
{
    /**
     * How many identifiers of a key are looked up in its model.
     */
    public const SAMPLE = 200;

    /**
     * The plain casts of JSON attributes.
     *
     * @var list<string>
     */
    public const JSON_CASTS = ['json', 'array', 'object', 'collection', AsArrayObject::class, AsCollection::class];

    public function title(): string
    {
        return 'Stored JSON references';
    }

    public function check(Site $site): iterable
    {
        $models = $site->models();
        if (count($models) === 0) {
            yield Finding::info('No model was found to read: pass them with --model.');

            return;
        }

        /** @var array<string, list<string>> $identifiers */
        $identifiers = [];
        /** @var array<string, list<string>> $locations */
        $locations = [];
        foreach ($models as $class) {
            $model = new $class;
            foreach ($this->jsonAttributes($model) as $attribute => $cast) {
                try {
                    $references = $this->references($model, $attribute, $site->limit);
                } catch (Throwable $e) {
                    yield Finding::info("`{$class}::{$attribute}` could not be read: ".$e->getMessage());

                    continue;
                }
                if (count($references) === 0) {
                    continue;
                }

                $paths = [];
                foreach ($references as $path => $keys) {
                    foreach ($keys as $key => $found) {
                        $paths[] = "{$attribute}.{$path} → {$key}:// ({$found['count']})";
                        $identifiers[$key] = array_slice(array_values(array_unique([...$identifiers[$key] ?? [], ...$found['identifiers']])), 0, self::SAMPLE);
                        $locations[$key][] = "{$class}::{$attribute}";
                    }
                }

                if ($cast === null) {
                    yield Finding::todo(
                        "`{$class}::{$attribute}` holds references that the cast doesn't know: it isn't cast with `JsonDataCast`, so the site's own code writes and syncs them.",
                        'Cast it with `JsonDataCast` and declare these paths, with their keys, in `getJsonDataRelations()` or `jsonDataSchema()`.',
                        $paths,
                    );
                } else {
                    yield Finding::info("`{$class}::{$attribute}` is cast with `JsonDataCast` and holds references.", null, $paths);
                }
            }
        }

        foreach ($identifiers as $key => $sample) {
            yield from $this->checkKey($site, $key, $sample, array_values(array_unique($locations[$key] ?? [])));
        }

        if (isset($identifiers['medias']) && $site->config('site.medias.reference_key', 'media') !== 'medias') {
            yield Finding::todo(
                'The site stores `medias://` references, and 2.0 writes `media://` by default.',
                'Set `site.medias.reference_key` to `medias`, so that the site keeps writing what its front ends read.',
            );
        }
    }

    /**
     * Whether 2.0 maps a key, and whether its identifiers match the model it maps to.
     *
     * @param  list<string>  $sample
     * @param  list<string>  $locations
     * @return iterable<Finding>
     */
    protected function checkKey(Site $site, string $key, array $sample, array $locations): iterable
    {
        $target = $this->targets($site)[$key] ?? null;
        if (is_null($target)) {
            $withRelation = array_filter($locations, function (string $location) use ($key) {
                $class = explode('::', $location)[0];

                return method_exists($class, $key);
            });
            $message = "`{$key}://` isn't mapped with `JsonData::map()`: without `enforceMap()`, it falls back to the relation `{$key}()` of its model, as in 1.x";
            $fix = "Map it before turning `enforceMap()` on: `JsonData::map(['{$key}' => Model::class])`.";
            yield count($withRelation) === count($locations)
                ? Finding::info($message.'.', $fix, $locations)
                : Finding::todo($message.', and a model without that relation resolves it to nothing.', $fix, $locations);

            return;
        }

        [$class, $column] = $target;
        $model = new $class;
        // An integer key can't be compared with a uid on every database (PostgreSQL)
        $candidates = $column === $model->getKeyName() && in_array($model->getKeyType(), ['int', 'integer'], true)
            ? array_values(array_filter($sample, fn (string $identifier) => ctype_digit($identifier)))
            : $sample;
        try {
            $found = count($candidates) === 0 ? 0 : $model->newQueryWithoutScopes()->whereIn($column, $candidates)->distinct()->count($column);
        } catch (Throwable $e) {
            yield Finding::info("The identifiers of `{$key}://` could not be looked up in `{$class}`: ".$e->getMessage());

            return;
        }

        $total = count($sample);
        $where = "`{$class}` by `{$column}`";
        if ($found === 0) {
            $numeric = count(array_filter($sample, fn (string $identifier) => ctype_digit($identifier))) === $total;
            yield Finding::breaking(
                "None of the {$total} `{$key}://` identifiers read match {$where}, which 2.0 maps `{$key}` to.".($numeric && $column !== $model->getKeyName() ? ' They look like ids.' : ''),
                $key === 'media' && $numeric
                    ? 'Rewrite them to `medias://`, the key of media by id: `json-data:rewrite-references --from=media --to=medias` once the models are cast with `JsonDataCast`, or with the site\'s own code before.'
                    : "Map `{$key}` to the model and column its identifiers are, or rewrite the references.",
                $locations,
            );
        } elseif ($found < $total) {
            yield Finding::info(
                ($total - $found)." of the {$total} `{$key}://` identifiers read match nothing in {$where}: references to deleted rows, which resolve to null.",
                null,
                $locations,
            );
        }
    }

    /**
     * The model and column of each key: the keys mapped with `JsonData::map()`, and the
     * keys of the medias module when it is off, as it will map them once it is on.
     *
     * @return array<string, array{class-string<Model>, string}>
     */
    protected function targets(Site $site): array
    {
        $targets = [];
        foreach (JsonData::keys() as $key => $target) {
            $targets[$key] = [$target->model, $target->column()];
        }

        $models = 'Folklore\\Mediatheque\\Support\\Models';
        if (! $site->moduleEnabled('medias') && class_exists($models)) {
            $media = $models::media();
            $targets['media'] ??= [$media, 'uid'];
            $targets['medias'] ??= [$media, (new $media)->getKeyName()];
        }

        return $targets;
    }

    /**
     * The JSON attributes of a model, with `JsonDataCast` or null for a plain cast.
     *
     * @return array<string, string|null>
     */
    protected function jsonAttributes(Model $model): array
    {
        $attributes = [];
        foreach ($model->getCasts() as $attribute => $cast) {
            $class = explode(':', $cast)[0];
            if (is_a($class, JsonDataCast::class, true) || is_a($class, LegacyJsonDataCast::class, true)) {
                $attributes[$attribute] = JsonDataCast::class;
            } elseif (in_array($class, self::JSON_CASTS, true)) {
                $attributes[$attribute] = null;
            }
        }

        return $attributes;
    }

    /**
     * The references of a JSON attribute in the model's rows, read as stored: by path,
     * with a `*` for list indexes, then by key, their count and identifiers.
     *
     * @return array<string, array<string, array{count: int, identifiers: list<string>}>>
     */
    protected function references(Model $model, string $attribute, int $limit): array
    {
        $query = $model->newQueryWithoutScopes()->toBase()->select([$attribute])->whereNotNull($attribute)->orderBy($model->getKeyName());
        if ($limit > 0) {
            $query->limit($limit);
        }

        $references = [];
        foreach ($query->cursor() as $row) {
            $raw = ((array) $row)[$attribute] ?? null;
            $value = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($value)) {
                $this->collect($value, '', $references);
            }
        }
        ksort($references);

        return $references;
    }

    /**
     * @param  array<mixed>  $value
     * @param  array<string, array<string, array{count: int, identifiers: list<string>}>>  $references
     */
    protected function collect(array $value, string $prefix, array &$references): void
    {
        foreach ($value as $segment => $item) {
            $path = $prefix.(is_int($segment) ? '*' : $segment);
            if (is_array($item)) {
                $this->collect($item, $path.'.', $references);
            } elseif (is_string($item) && preg_match('#^('.JsonData::KEY_PATTERN.')://(\S+)$#', $item, $match) === 1) {
                if (in_array(strtolower($match[1]), JsonCodeCheck::URL_SCHEMES, true)) {
                    continue;
                }
                $found = $references[$path][$match[1]] ?? ['count' => 0, 'identifiers' => []];
                $found['count']++;
                if (count($found['identifiers']) < self::SAMPLE && ! in_array($match[2], $found['identifiers'], true)) {
                    $found['identifiers'][] = $match[2];
                }
                $references[$path][$match[1]] = $found;
            }
        }
    }
}
