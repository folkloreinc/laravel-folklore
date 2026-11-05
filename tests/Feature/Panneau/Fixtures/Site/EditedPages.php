<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures\Site;

use Folklore\Panneau\Resources\Pages;
use Panneau\Fields\Field;

/**
 * Exposes the helpers of `EditsFields` to the tests.
 */
class EditedPages extends Pages
{
    /**
     * @param  list<Field>  $fields
     * @return list<Field>
     */
    public static function before(array $fields, string $name, Field ...$new): array
    {
        return static::insertBefore($fields, $name, ...$new);
    }

    /**
     * @param  list<Field>  $fields
     * @return list<Field>
     */
    public static function replace(array $fields, Field $field): array
    {
        return static::replaceField($fields, $field);
    }
}
