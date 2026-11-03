<?php

namespace Folklore\Models\Concerns;

use ReflectionMethod;

/**
 * For a model of the package that declares its JSON data with a schema and still has the
 * 1.x `getJsonDataRelations()`, for its subclasses: a subclass that overrides it keeps its
 * 1.x declarations until it moves to `jsonDataSchema()`, since a schema would replace them.
 */
trait KeepsSubclassJsonDataRelations
{
    /**
     * @var array<class-string, bool>
     */
    private static array $overridesJsonDataRelations = [];

    /**
     * Whether the model's class overrides the `getJsonDataRelations()` of the package's model.
     */
    protected function overridesJsonDataRelations(): bool
    {
        return self::$overridesJsonDataRelations[static::class] ??=
            (new ReflectionMethod($this, 'getJsonDataRelations'))->getDeclaringClass()->getName() !== self::class;
    }
}
