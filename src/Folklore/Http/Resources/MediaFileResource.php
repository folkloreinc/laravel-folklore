<?php

namespace Folklore\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaFileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id(),
            'handle' => $this->handle(),
            'name' => $this->name(),
            'url' => $this->url(),
            'mime' => $this->mime(),
            'size' => $this->size(),
        ];
    }
}
