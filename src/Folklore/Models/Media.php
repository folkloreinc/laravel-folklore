<?php

namespace Folklore\Models;

use Folklore\Mediatheque\Models\Media as BaseMedia;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Contracts\Resources\Media as MediaContract;
use Folklore\Resources\Media as MediaResource;
use Folklore\Resources\Image as ImageResource;
use Folklore\Resources\Video as VideoResource;
use Folklore\Resources\Audio as AudioResource;

class Media extends BaseMedia implements Resourcable
{
    public function toResource(): MediaContract
    {
        if ($this->type === 'image') {
            return new ImageResource($this);
        }
        if ($this->type === 'video') {
            return new VideoResource($this);
        }
        if ($this->type === 'audio') {
            return new AudioResource($this);
        }
        return new MediaResource($this);
    }
}
