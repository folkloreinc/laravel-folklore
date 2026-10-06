<?php

namespace Folklore\Models;

use Folklore\Contracts\Entities\MediaFile as MediaFileContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Entities\MediaFile as MediaFileEntity;
use Folklore\Mediatheque\Models\File as BaseFile;

class MediaFile extends BaseFile implements ToEntity
{
    public function toEntity(): MediaFileContract
    {
        return new MediaFileEntity($this);
    }
}
