<?php

namespace Folklore\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use Folklore\Contracts\Resources\Image;

class MediaResource extends JsonResource
{
    protected $withFiles = true;

    protected $withMetadata = true;

    protected $withImageSizes = true;

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
            'type' => $this->type(),
            'name' => $this->name(),
            'url' => $this->url(),
            'thumbnail_url' => $this->thumbnailUrl(),
            'metadata' => $this->when($this->withMetadata, function () {
                return new MediaMetadataResource($this->metadata());
            }),
            'sizes' => $this->when(
                $this->withImageSizes && $this->resource instanceof Image,
                function () {
                    return ImageSizeResource::collection($this->sizes());
                }
            ),
            'files' => $this->when($this->withFiles, function () {
                return MediaFileResource::collection($this->files());
            }),
        ];
    }

    public function withoutMetadata()
    {
        $this->withMetadata = false;
        return $this;
    }

    public function withMetadata()
    {
        $this->withMetadata = true;
        return $this;
    }

    public function withoutFiles()
    {
        $this->withFiles = false;
        return $this;
    }

    public function withFiles()
    {
        $this->withFiles = true;
        return $this;
    }

    public function withoutImageSizes()
    {
        $this->withImageSizes = false;
        return $this;
    }

    public function withImageSizes()
    {
        $this->withImageSizes = true;
        return $this;
    }
}
