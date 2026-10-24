<?php

namespace Folklore\Eloquent;

use Closure;
use Folklore\JsonData\JsonDataCast as BaseJsonDataCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;

/**
 * laravel-json-data's cast, with the 1.x macros (docs/decisions/0021): string-keyed
 * `get`, `set` and `before_set` callables, for a relation or for a model and a relation
 * (`JsonDataCast::macro('medias:get', …)`, `'App\Models\Game:medias:before_set'`). The
 * adapter of the 1.x declarations passes them to the cast through the `callbacks` option.
 *
 * @deprecated Cast with Folklore\JsonData\JsonDataCast, and use handlers instead of macros.
 * Removed in 3.0.
 */
class JsonDataCast extends BaseJsonDataCast
{
    use Macroable;

    /**
     * The `callbacks` option that resolves the macros of a relation, the model's first, or
     * null when no macro is set.
     *
     * @return (Closure(string, string, Model): mixed)|null
     */
    public static function macroCallbacks(): ?Closure
    {
        if (count(static::$macros) === 0) {
            return null;
        }

        return static fn (string $name, string $relationName, Model $model): mixed => static::$macros[$model::class.':'.$relationName.':'.$name]
            ?? static::$macros[$relationName.':'.$name]
            ?? null;
    }
}
