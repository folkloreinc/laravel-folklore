<?php

namespace App\Http\Resources;

use Folklore\Http\Resources\MediaResource;
use Illuminate\Http\Request;

class ResourceWrites extends MediaResource
{
    public function toArray(Request $request): array
    {
        if (! isset($this->resource)) {
            return [];
        }
        $this->resource ??= $request->user();
        $media = &$this->resource;
        unset($this->resource);

        return ['name' => $media->name()];
    }
}
