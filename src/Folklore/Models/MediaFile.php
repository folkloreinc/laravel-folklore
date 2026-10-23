<?php

namespace Folklore\Models;

use Folklore\Contracts\Entities\MediaFile as MediaFileContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Entities\MediaFile as MediaFileEntity;
use Folklore\Mediatheque\Models\File as BaseFile;

/**
 * The file model of the medias module, on laravel-mediatheque 2. The module sets it in
 * `config('mediatheque.models.file')` unless the site sets its own.
 */
class MediaFile extends BaseFile implements ToEntity
{
    public function toEntity(): MediaFileContract
    {
        return new MediaFileEntity($this);
    }
}
