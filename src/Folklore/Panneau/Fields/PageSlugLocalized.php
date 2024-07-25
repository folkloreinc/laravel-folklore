<?php

namespace Folklore\Panneau\Fields;

use Panneau\Support\LocalizedField;

class PageSlugLocalized extends LocalizedField
{
    public function component(): string
    {
        return 'page-slug-localized';
    }

    public function field($locale)
    {
        $field = new PageSlug($locale);
        if ($this->disabled) {
            $field->isDisabled();
        }
        return $field;
    }
}
