<?php

namespace Folklore\Tests\Feature\Pages\Fixtures;

use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Models\Page;

/**
 * A site's page that describes its own keys on top of the package's schema.
 */
class DescribedPage extends Page
{
    protected $table = 'pages';

    public function jsonDataSchema(string $attribute): ?ObjectType
    {
        $schema = parent::jsonDataSchema($attribute);
        if (is_null($schema)) {
            return null;
        }

        return Json::object([
            ...$schema->getProperties(),
            'title' => Json::string()->translatable(),
        ])->additional(false);
    }
}
