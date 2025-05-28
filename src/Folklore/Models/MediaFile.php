<?php

namespace Folklore\Models;

use Folklore\Mediatheque\Models\File as BaseFile;
use Folklore\Contracts\Entities\MediaFile as MediaFileContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Entities\MediaFile as MediaFileEntity;

class MediaFile extends BaseFile implements ToEntity
{
    public function toEntity(): MediaFileContract
    {
        return new MediaFileEntity($this);
    }
}
