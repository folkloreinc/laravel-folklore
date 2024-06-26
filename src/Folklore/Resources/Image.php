<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\Image as ImageContract;
use Folklore\Contracts\Resources\ImageMetadata as ImageMetadataContract;
use Illuminate\Support\Collection;
use Folklore\Image\Facade as ImageFacade;

class Image extends Media implements ImageContract
{
    protected $sizes;

    protected $filters;

    public function url(): string
    {
        $url = parent::url();
        if (isset($this->filters)) {
            $path = parse_url($url, PHP_URL_PATH);
            return rtrim(config('app.url'), '/') . ImageFacade::url($path, $this->filters);
        }
        return $url;
    }

    public function urlWithoutFilters(): string
    {
        return parent::url();
    }

    public function sizes(): Collection
    {
        if (!isset($this->sizes)) {
            $this->sizes = collect(config('image.sizes'))->map(function ($filter) {
                return new ImageSize($this, $filter, null, $this->filters);
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

    public function setFilters($filters): ImageContract
    {
        $this->filters = $filters;
        return $this;
    }
}
