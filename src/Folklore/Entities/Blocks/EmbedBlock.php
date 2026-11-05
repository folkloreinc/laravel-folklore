<?php

namespace Folklore\Entities\Blocks;

use Folklore\Contracts\Pages\DeclaresBlockFields;
use Folklore\Entities\Block;
use Folklore\Entities\Blocks\Concerns\ReadsBlockData;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Pages\BlockLevel;
use Panneau\Fields\Embed;

/**
 * A video, a sound or a post of another site, by its URL, with what its provider's oEmbed
 * gives (Panneau's `EmbedValue`: `url`, `provider`, `title`, `html`, `thumbnail`…). A
 * standard block type (docs/decisions/0025).
 */
class EmbedBlock extends Block implements DeclaresBlockFields
{
    use ReadsBlockData;

    public static function blockSchema(BlockLevel $level): ObjectType
    {
        return Json::object([
            // What the provider gives changes with it: only the URL is required
            'embed' => Json::object(['url' => Json::string()])->additional()->nullable(),
        ])->additional(false);
    }

    public static function blockLabel(): string
    {
        return self::adminLabel('blocks.embed');
    }

    public static function blockIcon(): ?string
    {
        return 'code';
    }

    public static function blockFields(): array
    {
        return [
            Embed::make('embed')->label(self::adminLabel('fields.embed')),
        ];
    }

    /**
     * The embed's value: its `url`, and what its provider gives.
     *
     * @return array<array-key, mixed>|null
     */
    public function embed(): ?array
    {
        $embed = data_get($this->data, 'embed');

        return is_array($embed) ? $embed : null;
    }

    public function url(): ?string
    {
        return $this->string('embed.url');
    }
}
