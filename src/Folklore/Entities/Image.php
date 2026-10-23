<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\Image as ImageContract;
use Folklore\Contracts\Entities\ImageMetadata as ImageMetadataContract;
use Folklore\Image\Exceptions\ImageException;
use Folklore\Image\Facades\Image as ImageFacade;
use Folklore\Image\PendingImage;
use Illuminate\Support\Collection;

class Image extends Media implements ImageContract
{
    protected $sizes;

    protected $filters;

    /**
     * The original's URL, or, with filters, the URL of that rendition from laravel-image.
     */
    public function url(): string
    {
        $url = parent::url();
        if (isset($this->filters) && $url !== '') {
            return $this->pendingImage($this->filters)?->url() ?? $url;
        }

        return $url;
    }

    public function urlWithoutFilters(): string
    {
        return parent::url();
    }

    /**
     * One size per preset of laravel-image (`config('image.presets')`).
     */
    public function sizes(): Collection
    {
        if (! isset($this->sizes)) {
            $this->sizes = collect(array_keys(ImageFacade::presets()->all()))
                ->map(fn (string $preset) => new ImageSize($this, $preset, null, $this->filters))
                ->values();
        }

        return $this->sizes;
    }

    public function metadata(): ImageMetadataContract
    {
        if (! isset($this->metadata)) {
            $this->metadata = new ImageMetadata($this->model);
        }

        return $this->metadata;
    }

    /**
     * A rendition for `url()`, in the terms laravel-image 2 understands: a preset name,
     * or `width`, `height`, `fit`, `format` and `quality`. The other filters of laravel-image
     * 1.x are ignored.
     *
     * @deprecated Use the image's sizes, or `Image::for()` on its model.
     */
    public function setFilters($filters): ImageContract
    {
        $this->filters = $filters;
        $this->sizes = null;

        return $this;
    }

    /**
     * The laravel-image builder for this image with filters, or null when laravel-image
     * can't render it.
     */
    public function pendingImage(mixed $filters = null): ?PendingImage
    {
        try {
            $image = ImageFacade::for($this->model);
            if (is_string($filters)) {
                return $image->preset($filters);
            }
            if (! is_array($filters)) {
                return $image;
            }
            foreach ($filters as $key => $value) {
                $image = match (true) {
                    is_int($key) && is_string($value) && ImageFacade::presets()->has($value) => $image->preset($value),
                    $key === 'width' && is_numeric($value) => $image->width((int) $value),
                    $key === 'height' && is_numeric($value) => $image->height((int) $value),
                    $key === 'fit' && is_string($value) => $image->fit($value),
                    $key === 'format' && is_string($value) => $image->format($value),
                    $key === 'quality' && is_numeric($value) => $image->quality((int) $value),
                    default => $image,
                };
            }

            return $image;
        } catch (ImageException) {
            return null;
        }
    }
}
