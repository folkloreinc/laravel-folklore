<?php

namespace Folklore\Eloquent;

use Folklore\Contracts\Eloquent\HasJsonDataColumnExtract;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Gives laravel-json-data's cast the 1.x declarations of a model, through
 * `JsonData::declareUsing()` (docs/decisions/0021): the paths of
 * `getJsonDataRelations()`, the columns of `getJsonDataColumnExtract()`, and the macros of
 * `Folklore\Eloquent\JsonDataCast` through the `callbacks` option.
 *
 * @deprecated With the 1.x declarations, until 3.0: a model declares its JSON columns with
 * `jsonDataSchema()`.
 */
final class LegacyJsonDataDeclarations
{
    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array{references: array<array-key, mixed>, columns: array<string, string>}|null
     */
    public function __invoke(Model $model, string $attribute, mixed $value, array $attributes): ?array
    {
        if (! $model instanceof HasJsonDataRelations && ! $model instanceof HasJsonDataColumnExtract) {
            return null;
        }

        $references = [];
        if ($model instanceof HasJsonDataRelations) {
            $declared = $model->getJsonDataRelations($attribute, $value, $attributes);
            $references = $declared instanceof Collection ? $declared->all() : (is_array($declared) ? $declared : []);
        }

        $callbacks = JsonDataCast::macroCallbacks();
        if (! is_null($callbacks)) {
            $references = array_map(
                fn (mixed $options): mixed => match (true) {
                    is_array($options) => $options + ['callbacks' => $callbacks],
                    is_string($options) => ['relation' => $options, 'callbacks' => $callbacks],
                    default => $options,
                },
                $references,
            );
        }

        $columns = [];
        if ($model instanceof HasJsonDataColumnExtract) {
            $declared = $model->getJsonDataColumnExtract($attribute, $value, $attributes);
            foreach (is_array($declared) ? $declared : [] as $path => $column) {
                if (is_string($column)) {
                    $columns[(string) $path] = $column;
                }
            }
        }

        return ['references' => $references, 'columns' => $columns];
    }
}
