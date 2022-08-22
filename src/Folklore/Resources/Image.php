<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\Image as ImageContract;
use Folklore\Contracts\Resources\ImageMetadata as ImageMetadataContract;
use Illuminate\Support\Collection;

class Image extends Media implements ImageContract
{
    protected $sizes;

    public function sizes(): Collection
    {
        if (!isset($this->sizes)) {
            $this->sizes = collect(config('image.sizes'))->map(function ($filter) {
                return new ImageSize($this, $filter);
            });
        }
        return $this->sizes;
    }

    public function metadata(): ImageMetadataContract
    {
        if (!isset($this->metadata)) {
            $this->metadata = new ImageMetadata($this->model);
        }
        return $this->metadata;
    }
}
