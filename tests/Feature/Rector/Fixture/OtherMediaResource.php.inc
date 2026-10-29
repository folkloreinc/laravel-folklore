<?php

namespace App\Http\Resources;

use Folklore\Mediatheque\Http\Resources\MediaResource;

class OtherMediaResource extends MediaResource
{
    public function toArray($request): array
    {
        return ['name' => $this->resource->name()];
    }
}
