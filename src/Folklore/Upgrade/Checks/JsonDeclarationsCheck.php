<?php

namespace Folklore\Upgrade\Checks;

use Folklore\Upgrade\Finding;
use Folklore\Upgrade\JsonSchemaConversion;
use Folklore\Upgrade\JsonSchemaConverter;
use Folklore\Upgrade\ModelFile;
use Folklore\Upgrade\Occurrence;
use Folklore\Upgrade\Site;
use Throwable;

/**
 * The models that declare their JSON data with the 1.x declarations,
 * `getJsonDataRelations()` and `getJsonDataColumnExtract()`, which `folklore:upgrade
 * --schemas` converts to `jsonDataSchema()`: what it converts, with the TODOs it writes,
 * and what it leaves, with why. And the repositories that declare `$jsonAttributeFillable`,
 * which keeps them filling the 1.x way.
 */
class JsonDeclarationsCheck implements Check
{
    public function __construct(protected ?JsonSchemaConverter $converter = null) {}

    public function title(): string
    {
        return 'JSON declarations';
    }

    public function check(Site $site): iterable
    {
        $converter = $this->converter ?? new JsonSchemaConverter;
        $conversions = [];
        foreach ($site->models() as $class) {
            try {
                $conversion = $converter->convert($site, $class);
            } catch (Throwable $e) {
                yield Finding::info("The JSON declarations of `{$class}` could not be read: ".$e->getMessage());

                continue;
            }
            if (! is_null($conversion)) {
                $conversions[$conversion->model] = $conversion;
            }
        }

        if (count($conversions) > 0) {
            $converted = count(array_filter($conversions, fn (JsonSchemaConversion $conversion) => $conversion->converts()));
            yield Finding::info(sprintf(
                '%d of the %d models with 1.x JSON declarations convert to `jsonDataSchema()`, which reads and writes the same data. The others keep their declarations until 3.0.',
                $converted,
                count($conversions),
            ));
        }

        foreach ($conversions as $conversion) {
            yield $this->finding($conversion);
        }

        yield from $this->repositories($site, $conversions);
    }

    protected function finding(JsonSchemaConversion $conversion): Finding
    {
        $methods = implode(' and ', array_map(fn (string $method) => "`{$method}()`", $conversion->methods));

        if ($conversion->converts()) {
            $todos = count($conversion->todos);

            return Finding::deprecated(
                "`{$conversion->model}` declares its JSON data with {$methods}: `folklore:upgrade --schemas` converts them to `jsonDataSchema()`".($todos > 0 ? ", with {$todos} `TODO(folklore-3)` to check." : '.'),
                'Run `php artisan folklore:upgrade --schemas --dry-run`, then without `--dry-run`, and read the schema it writes.',
                [$conversion->location(), ...array_map(fn (string $todo) => 'TODO(folklore-3): '.$todo, $conversion->todos)],
            );
        }

        $fix = count($conversion->keysToMap) > 0
            ? 'Map the keys, if the site can read their references by id rather than through the relation (docs/decisions/0016): `JsonData::map(['.implode(', ', array_map(
                fn (string $key, string $model) => "'{$key}' => \\{$model}::class",
                array_keys($conversion->keysToMap),
                $conversion->keysToMap,
            )).'])`, then run `folklore:upgrade --check` again. Or write the schema by hand, or keep the declarations until 3.0.'
            : 'Write the schema by hand (`jsonDataSchema()`, docs/json-data.md), or keep the declarations until 3.0.';

        return Finding::deprecated(
            "`{$conversion->model}` keeps {$methods}: `folklore:upgrade --schemas` doesn't convert them, since ".implode('; ', $conversion->blockers).'.',
            $fix,
            [$conversion->location()],
        );
    }

    /**
     * The repositories that declare `$jsonAttributeFillable`, with their model when it is
     * found in `newModel()`.
     *
     * @param  array<string, JsonSchemaConversion>  $conversions
     * @return iterable<Finding>
     */
    protected function repositories(Site $site, array $conversions): iterable
    {
        $locations = [];
        foreach ($site->matches('/^\s*(?:protected|public)\s+\$jsonAttributeFillable\s*=/') as $occurrence) {
            $model = $this->modelOf($occurrence);
            $conversion = is_null($model) ? null : ($conversions[$model] ?? null);
            $status = match (true) {
                is_null($model) => '',
                is_null($conversion) => " ({$model})",
                $conversion->converts() => " ({$model}, converted)",
                default => " ({$model}, not converted)",
            };
            $locations[] = $occurrence->location().$status;
        }

        if (count($locations) > 0) {
            yield Finding::deprecated(
                '`$jsonAttributeFillable` keeps these repositories filling their JSON data the 1.x way, even once their model has a schema.',
                'Once a model\'s schema names every key that its repository stores, remove `$jsonAttributeFillable` and `$jsonAttributeExclude`: the repository then fills with `JsonData::fill()`, which stores the keys of the schema only.',
                $locations,
            );
        }
    }

    /**
     * The model that a repository's `newModel()` makes.
     */
    protected function modelOf(Occurrence $occurrence): ?string
    {
        $code = $occurrence->file->code();
        if (preg_match('/function\s+newModel\s*\([^)]*\)[^{]*\{[^}]*?\bnew\s+(\\\\?[\w\\\\]+)/', $code, $match) !== 1) {
            return null;
        }

        try {
            return (new ModelFile($code))->resolve($match[1]);
        } catch (Throwable) {
            return null;
        }
    }
}
