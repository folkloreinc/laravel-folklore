<?php

namespace Folklore\Models;

use Folklore\Contracts\Entities\Media as MediaContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Entities\Audio as AudioEntity;
use Folklore\Entities\Document as DocumentEntity;
use Folklore\Entities\Image as ImageEntity;
use Folklore\Entities\Media as MediaEntity;
use Folklore\Entities\Video as VideoEntity;
use Folklore\Mediatheque\Models\Media as BaseMedia;
use Folklore\Support\Concerns\HasTypedEntity;

/**
 * The media model of the medias module, on laravel-mediatheque 2. The module sets it in
 * `config('mediatheque.models.media')` unless the site sets its own.
 */
class Media extends BaseMedia implements ToEntity
{
    use HasTypedEntity;

    /**
     * What repositories fill from their data. The mediatheque's model guards every column.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'data'];

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
