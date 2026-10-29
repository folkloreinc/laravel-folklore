<?php

namespace App\Http\Resources;

use Folklore\Http\Resources\MediaResource as BaseMediaResource;

class ImageResource extends BaseMediaResource
{
    public function toArray($request)
    {
        return array_merge(parent::toArray($request), [
            'count' => $this->files()->count(),
            'large' => $this->when($this->hasLarge(), fn () => $this->resource->sizes()->first()),
            'credits' => $this->credits(),
        ]);
    }

    protected function hasLarge(): bool
    {
        return $this->mergeWhen(true, []) !== null && $this->withoutFiles() !== null;
    }
}
