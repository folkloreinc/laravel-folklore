<?php

namespace Folklore\Models;

use Folklore\Mediatheque\Models\File as BaseFile;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Contracts\Resources\MediaFile as MediaFileContract;
use Folklore\Resources\MediaFile as MediaFileResource;

class MediaFile extends BaseFile implements Resourcable
{
    public function toResource(): MediaFileContract
    {
        return new MediaFileResource($this);
    }
}
