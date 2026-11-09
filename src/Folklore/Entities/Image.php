<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\Image as ImageContract;
use Folklore\Contracts\Entities\ImageMetadata as ImageMetadataContract;
use Folklore\Image\Exceptions\ImageException;
use Folklore\Image\Facades\Image as ImageFacade;
use Folklore\Image\PendingImage;
use Illuminate\Support\Collection;

/**
 * An image of the media library. With a transform (`withTransform()`), the edits of the image
 * for one use, such as the crop chosen in the admin for the image of a block, its URL and its
 * sizes are of the edited image, as laravel-image renders a transform
 * (`Image::for($media)->transform(...)`).
 */
class Image extends Media implements ImageContract
{
    protected $sizes;

    protected $filters;

    /**
     * The edits of the image for this use: Panneau's `MediaTransform`, as JSON data keeps it.
     *
     * @var array<array-key, mixed>|null
     */
    protected ?array $transform = null;

    /**
     * The image of a value of JSON data, as the cast resolved it: a media model or entity, or a
     * media reference with properties (`Json::media(transform: true)`), whose `id` is the media
     * and whose `transform` the edits of the image for this use. Null for anything else.
     */
    public static function fromValue(mixed $value): ?ImageContract
    {
        $transform = null;
        if (is_array($value) && array_key_exists('id', $value)) {
            $transform = is_array($value['transform'] ?? null) && count($value['transform']) > 0 ? $value['transform'] : null;
            $value = $value['id'];
        }
        $image = to_entity($value);
        if (! $image instanceof ImageContract) {
            return null;
        }

        return $transform !== null && $image instanceof self ? $image->withTransform($transform) : $image;
    }

    /**
     * A copy of the image with the edits of the image for this use (Panneau's
     * `MediaTransform`: crop, rotate, flips, focal point, and its `base`), or without any.
     *
     * @param  array<array-key, mixed>|null  $transform
     */
    public function withTransform(?array $transform): static
    {
        $image = clone $this;
        $image->transform = $transform !== null && count($transform) > 0 ? $transform : null;
        $image->sizes = null;

        return $image;
    }

    /**
     * The edits of the image for this use, or null.
     *
     * @return array<array-key, mixed>|null
     */
    public function transform(): ?array
    {
        return $this->transform;
    }

    /**
     * The original's URL, or, with filters or a transform, the URL of that rendition from
     * laravel-image. With `site.medias.image_urls` set to `legacy`, a transform alone keeps
     * the original's URL: 1.x URLs can't crop.
     */
    public function url(): string
    {
        $url = parent::url();
        if ($url !== '' && (isset($this->filters) || ($this->transform !== null && ! ImageSize::usesLegacyUrls()))) {
            return $this->pendingImage($this->filters)?->url() ?? $url;
        }

        return $url;
    }

    public function urlWithoutFilters(): string
    {
        return parent::url();
    }

    /**
     * One size per preset of laravel-image (`config('image.presets')`), of the image with its
     * transform, or, with `site.medias.image_urls` set to `legacy`, one per size of
     * `image.sizes` when it is set, as in 1.1, whose URLs can't crop.
     */
    public function sizes(): Collection
    {
        if (! isset($this->sizes)) {
            $legacy = ImageSize::usesLegacyUrls() ? ImageSize::legacySizeIds() : [];
            $this->sizes = collect(count($legacy) > 0 ? $legacy : array_keys(ImageFacade::presets()->all()))
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
     * The laravel-image builder for this image with its transform and filters, or null when
     * laravel-image can't render it, or its transform is invalid.
     */
    public function pendingImage(mixed $filters = null): ?PendingImage
    {
        try {
            $image = ImageFacade::for($this->model);
            if ($this->transform !== null) {
                // A transform is a free spec, which strict mode serves signed: without
                // expiry, so that the URL stays the same
                $image = $image->transform($this->transform)->signed(false);
            }
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
