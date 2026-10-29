<?php

namespace App\Http\Resources;

use Folklore\Http\Resources\MediaResource as BaseMediaResource;

class ImageResource extends BaseMediaResource
{
    public function toArray($request)
    {
        return array_merge(parent::toArray($request), [
            'count' => $this->files()->count(),
        ]);
    }
}
