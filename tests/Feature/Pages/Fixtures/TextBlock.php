<?php

namespace Folklore\Tests\Feature\Pages\Fixtures;

use Folklore\Entities\Block;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Pages\BlockLevel;

/**
 * A block of text, with an image.
 */
class TextBlock extends Block
{
    public static function blockSchema(BlockLevel $level): ObjectType
    {
        return Json::object([
            'body' => Json::string(),
            'image' => Json::media(),
        ])->additional(false);
    }

    public function body(): ?string
    {
        $body = $this->data['body'] ?? null;

        return is_string($body) ? $body : null;
    }
}
