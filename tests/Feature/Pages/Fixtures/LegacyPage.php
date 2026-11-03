<?php

namespace Folklore\Tests\Feature\Pages\Fixtures;

use Folklore\Models\Page;

/**
 * A 1.1 site's page that adds to the package's 1.x declarations.
 */
class LegacyPage extends Page
{
    protected $table = 'pages';

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return parent::getJsonDataRelations($key, $value, $attributes);
    }
}
