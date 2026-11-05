<?php

namespace Folklore\Support;

use Folklore\JsonData\Schema\ListType;
use Folklore\JsonData\Schema\MapType;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\JsonData\Schema\ScalarType;
use Folklore\JsonData\Schema\Type;
use Folklore\JsonData\Schema\UnionType;

/**
 * Keeps the locales that a save doesn't give. A form that edits some locales of a value
 * (`['fr' => …]`) mustn't remove the others (`en`): each translatable value of a JSON schema
 * gets back, from the stored value at its path, the locales it doesn't have. The items of a
 * list are matched by their `id`, as blocks are, at every level, and those of a union only
 * when they keep their type.
 *
 * A locale that the save gives keeps its value, `null` included.
 */
final class OtherLocales
{
    /**
     * The value of a node, with the locales of its translatable values that only the stored
     * value has.
     */
    public static function keep(Type $node, mixed $value, mixed $stored): mixed
    {
        if (! is_array($value) || ! is_array($stored)) {
            return $value;
        }

        if ($node instanceof ScalarType) {
            return $node->isTranslatable() && ! array_is_list($value) && ! array_is_list($stored)
                ? $value + $stored
                : $value;
        }

        if ($node instanceof UnionType) {
            $discriminator = $node->getDiscriminator();
            $type = $value[$discriminator] ?? null;
            $variant = $node->getVariant($type);

            return $variant !== null && $type === ($stored[$discriminator] ?? null)
                ? self::keep($variant, $value, $stored)
                : $value;
        }

        if ($node instanceof ObjectType) {
            foreach ($node->getProperties() as $name => $property) {
                if (array_key_exists($name, $value) && array_key_exists($name, $stored)) {
                    $value[$name] = self::keep($property, $value[$name], $stored[$name]);
                }
            }

            return $value;
        }

        if ($node instanceof ListType) {
            $storedById = [];
            foreach ($stored as $item) {
                $id = is_array($item) ? self::idOf($item['id'] ?? null) : null;
                if ($id !== null) {
                    $storedById[$id] = $item;
                }
            }
            foreach ($value as $index => $item) {
                $id = is_array($item) ? self::idOf($item['id'] ?? null) : null;
                if ($id !== null && isset($storedById[$id])) {
                    $value[$index] = self::keep($node->getOf(), $item, $storedById[$id]);
                }
            }

            return $value;
        }

        if ($node instanceof MapType) {
            foreach ($value as $key => $item) {
                if (array_key_exists($key, $stored)) {
                    $value[$key] = self::keep($node->getOf(), $item, $stored[$key]);
                }
            }
        }

        return $value;
    }

    private static function idOf(mixed $id): ?string
    {
        return is_int($id) || (is_string($id) && $id !== '') ? (string) $id : null;
    }
}
