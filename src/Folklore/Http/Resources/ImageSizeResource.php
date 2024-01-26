<?php

namespace Folklore\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ImageSizeResource extends JsonResource
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
            'url' => $this->url(),
            'width' => $this->width(),
            'height' => $this->height(),
            'mime' => $this->mime(),
        ];
    }
}
