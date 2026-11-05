<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures\Site;

use Folklore\Panneau\Fields\Blocks;
use Folklore\Panneau\Resources\Pages;
use Panneau\Fields\Field;
use Panneau\Fields\Text;

/**
 * A site's pages with a subtitle after the title, without descriptions, and without the
 * gallery blocks: what a site overrides without copying the methods.
 */
class PagesWithSubtitle extends Pages
{
    public function fields(): array
    {
        return static::withoutFields(
            static::insertAfter(parent::fields(), 'title', Text::make('subtitle')->localized()),
            'description',
        );
    }

    protected function blocksField(): Field
    {
        $field = parent::blocksField();

        return $field instanceof Blocks ? $field->exceptTypes('gallery') : $field;
    }
}
