<?php

namespace App\Http\Resources;

use Folklore\Http\Resources\MediaResource;

class ArticleMediaResource extends MediaResource
{
    public function toArray($request)
    {
        return array_merge(parent::toArray($request), [
            'credits' => $this->credits(),
            'thumbnail' => $this->thumbnailUrl(),
            'large' => $this->when($this->resource->isImage(), fn () => $this->large()),
        ]);
    }

    protected function credits(): ?string
    {
        return $this->metadata()->get('credits');
    }
}
