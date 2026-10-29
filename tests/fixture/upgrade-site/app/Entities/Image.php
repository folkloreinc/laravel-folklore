<?php

namespace App\Entities;

use Folklore\Entities\Image as BaseImage;
use Folklore\Entities\ImageSize;
use Illuminate\Support\Collection;

class Image extends BaseImage
{
    public function sizes(): Collection
    {
        return collect(config('image.sizes'))->map(fn ($filter) => new ImageSize($this, $filter, null));
    }

    public function large(): ImageSize
    {
        return new ImageSize($this, 'large');
    }

    public function description(): ?string
    {
        return $this->model->getMetadatas()->get('description');
    }

    public function thumbnail(): string
    {
        return $this->setFilters(['thumbnail'])->url();
    }
}
