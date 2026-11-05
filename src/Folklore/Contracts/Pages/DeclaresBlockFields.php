<?php

namespace Folklore\Contracts\Pages;

use Panneau\Fields\Field;

/**
 * The entity class of a block type, in `site.pages.blocks.types`, gives the type of the
 * `Blocks` field of the Panneau plugin: its label, icon and fields, which the panneau module's
 * `BlockTypes` registry offers without a registration of its own (docs/decisions/0025). The
 * standard block types of `Folklore\Entities\Blocks` implement it; a site extends them, or
 * implements it on its own types.
 *
 * Its methods are only called by the panneau module, in a request: the labels are in its
 * locale, and the fields are Panneau's.
 */
interface DeclaresBlockFields
{
    /**
     * The label of the type, in the current locale.
     */
    public static function blockLabel(): string;

    /**
     * The icon of the type in the admin, a name of Panneau's icons, or null.
     */
    public static function blockIcon(): ?string;

    /**
     * The fields of the type in the admin: `Panneau\Fields\Field`s, named as the properties of
     * the type's schema (`blockSchema()`).
     *
     * @return list<Field>
     */
    public static function blockFields(): array;
}
