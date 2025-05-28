<?php

namespace Folklore\Models;

use Folklore\Mediatheque\Models\Media as BaseMedia;
use Folklore\Contracts\Entities\Media as MediaContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Entities\Media as MediaEntity;
use Folklore\Entities\Image as ImageEntity;
use Folklore\Entities\Video as VideoEntity;
use Folklore\Entities\Audio as AudioEntity;
use Folklore\Entities\Document as DocumentEntity;
use Folklore\Support\Concerns\HasTypedEntity;

class Media extends BaseMedia implements ToEntity
{
    use HasTypedEntity;

    protected $entitiesByType = [
        'image' => ImageEntity::class,
        'video' => VideoEntity::class,
        'audio' => AudioEntity::class,
        'document' => DocumentEntity::class,
    ];

    public function toEntity(): MediaContract
    {
        return $this->toTypedEntity() ?? new MediaEntity($this);
    }
}
