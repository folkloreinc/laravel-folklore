<?php

namespace Folklore\Models;

use Folklore\Mediatheque\Models\Media as BaseMedia;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Contracts\Resources\Media as MediaContract;
use Folklore\Resources\Media as MediaResource;
use Folklore\Resources\Image as ImageResource;
use Folklore\Resources\Video as VideoResource;
use Folklore\Resources\Audio as AudioResource;
use Folklore\Support\Concerns\HasTypedResource;

class Media extends BaseMedia implements Resourcable
{
    use HasTypedResource;

    protected $typedResources = [
        'image' => ImageResource::class,
        'video' => VideoResource::class,
        'audio' => AudioResource::class,
    ];

    public function toResource(): MediaContract
    {
        return $this->toTypedResource() ?? new MediaResource($this);
    }
}
