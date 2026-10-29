<?php

namespace Folklore\Panneau\Fields;

use Folklore\Contracts\Entities\ToEntity;
use Panneau\Fields\Relation;

/**
 * A page: a Panneau `relation` to the pages resource, `pages` by default
 * (docs/decisions/0022).
 *
 * The pages it offers are those of the resource, within its scope, which Panneau also
 * validates. To offer some types of pages only, relate it to a pages resource whose scope has
 * these types (`['type' => ['section']]`): `Page::make('section')->resource(SectionsResource::class)`.
 */
class Page extends Relation
{
    /**
     * The id of the pages resource the field relates to by default.
     */
    public const RESOURCE = 'pages';

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->resource = self::RESOURCE;
    }

    /**
     * Turns Folklore models into their entities, which the pages resource's repository gives
     * ids to.
     */
    protected function serializeValue(mixed $value): mixed
    {
        $value = parent::serializeValue($value);
        if (is_array($value)) {
            return array_map(fn (mixed $page): mixed => $page instanceof ToEntity ? $page->toEntity() : $page, $value);
        }

        return $value instanceof ToEntity ? $value->toEntity() : $value;
    }
}
