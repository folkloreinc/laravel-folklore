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
        return new PageSlug($locale);
    }
}
