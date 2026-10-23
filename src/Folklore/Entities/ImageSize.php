<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\Image as ImageContract;
use Folklore\Contracts\Entities\ImageSize as ImageSizeContract;
use Folklore\Image\Exceptions\ImageException;
use Folklore\Image\Variants\ImageVariant;

/**
 * An image in one preset of laravel-image 2, with its URL, its exact dimensions and its type.
 */
class ImageSize implements ImageSizeContract
{
    /**
     * @var ImageContract
     */
    protected $image;

    /**
     * @var string
     */
    protected $size;

    protected $filters;

    /**
     * @var string|null
     */
    protected $format;

    protected $url;

    protected $dimension;

    protected ?ImageVariant $variant = null;

    protected bool $resolved = false;

    /**
     * @param  string  $size  The name of a laravel-image preset.
     * @param  string|null  $format  A format other than the preset's (`webp`, `avif`…).
     */
    public function __construct(ImageContract $image, string $size, ?string $format = null, $filters = null)
    {
        $this->image = $image;
        $this->size = $size;
        $this->format = $format;
        $this->filters = $filters;
    }

    public function id(): string
    {
        return $this->size;
    }

    /**
     * The URL of the preset, or the original's URL when laravel-image can't render it, or an
     * empty string without files.
     */
    public function url(): string
    {
        if (! isset($this->url)) {
            $original = $this->image->urlWithoutFilters();
            $this->url = $original === '' ? '' : ($this->variant()->url ?? $original);
        }

        return $this->url;
    }

    public function width(): int
    {
        return $this->variant()->width ?? $this->image->metadata()->width();
    }

    public function height(): int
    {
        return $this->variant()->height ?? $this->image->metadata()->height();
    }

    public function mime(): ?string
    {
        return $this->variant()?->mime() ?? $this->image->metadata()->mime();
    }

    /**
     * The variant of the preset, or null when laravel-image can't build it: no files, a
     * source without dimensions, or an unknown preset.
     */
    protected function variant(): ?ImageVariant
    {
        if (! $this->resolved) {
            $this->resolved = true;
            if (! $this->image instanceof Image || $this->image->urlWithoutFilters() === '') {
                return null;
            }
            try {
                $image = $this->image->pendingImage($this->filters)?->preset($this->size);
                if (! is_null($image) && ! is_null($this->format)) {
                    $image = $image->format($this->format);
                }
                $this->variant = $image?->variant();
            } catch (ImageException) {
                $this->variant = null;
            }
        }

        return $this->variant;
    }
}
