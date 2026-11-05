<?php

namespace Folklore\Panneau\Resources\Concerns;

use Closure;
use Illuminate\Container\Container;
use LogicException;
use Panneau\Fields\Field;

/**
 * Helpers for the standard resources and the resources that extend them
 * (docs/decisions/0025): change a list of fields without copying it, and read localized
 * values from entities, which have no public properties.
 *
 * ```php
 * public function fields(): array
 * {
 *     return static::insertAfter(parent::fields(), 'title', Text::make('subtitle')->localized());
 * }
 * ```
 */
trait EditsFields
{
    /**
     * The fields, with others after the field of this name.
     *
     * @param  list<Field>  $fields
     * @return list<Field>
     */
    protected static function insertAfter(array $fields, string $name, Field ...$new): array
    {
        $index = self::indexOfField($fields, $name);
        array_splice($fields, $index + 1, 0, $new);

        return $fields;
    }

    /**
     * The fields, with others before the field of this name.
     *
     * @param  list<Field>  $fields
     * @return list<Field>
     */
    protected static function insertBefore(array $fields, string $name, Field ...$new): array
    {
        $index = self::indexOfField($fields, $name);
        array_splice($fields, $index, 0, $new);

        return $fields;
    }

    /**
     * The fields, with a field in place of the field of its name.
     *
     * @param  list<Field>  $fields
     * @return list<Field>
     */
    protected static function replaceField(array $fields, Field $field): array
    {
        $fields[self::indexOfField($fields, $field->name())] = $field;

        return array_values($fields);
    }

    /**
     * The fields, without those of these names.
     *
     * @param  list<Field>  $fields
     * @return list<Field>
     */
    protected static function withoutFields(array $fields, string ...$names): array
    {
        return array_values(array_filter($fields, fn (Field $field): bool => ! in_array($field->name(), $names, true)));
    }

    /**
     * Reads a localized value from an item, for a field's `resolveUsing()`: the closure's value
     * in each locale of the admin (`panneau.locales`, or the app's locale).
     *
     * @param  Closure(object, string): mixed  $value
     * @return Closure(object): array<string, mixed>
     */
    protected static function localized(Closure $value): Closure
    {
        return function (object $item) use ($value): array {
            $values = [];
            foreach (self::adminLocales() as $locale) {
                $values[$locale] = $value($item, $locale);
            }

            return $values;
        };
    }

    /**
     * The locales of the admin: `panneau.locales`, or the app's locale.
     *
     * @return list<string>
     */
    protected static function adminLocales(): array
    {
        $config = Container::getInstance()->make('config');
        $locales = $config->get('panneau.locales');
        $locales = is_array($locales) ? array_values(array_filter($locales, fn (mixed $locale): bool => is_string($locale) && $locale !== '')) : [];
        $locale = $config->get('app.locale');

        return count($locales) > 0 ? $locales : [is_string($locale) ? $locale : 'en'];
    }

    /**
     * @param  list<Field>  $fields
     */
    private static function indexOfField(array $fields, string $name): int
    {
        foreach ($fields as $index => $field) {
            if ($field->name() === $name) {
                return $index;
            }
        }

        throw new LogicException(sprintf('%s has no field "%s".', static::class, $name));
    }

    /**
     * A translated label of the package (`folklore::panneau.*`).
     */
    protected static function trans(string $key): string
    {
        $label = __('folklore::panneau.'.$key);

        return is_string($label) ? $label : $key;
    }
}
