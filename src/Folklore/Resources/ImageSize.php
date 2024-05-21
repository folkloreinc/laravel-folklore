<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\Image as ImageContract;
use Folklore\Contracts\Resources\ImageSize as ImageSizeContract;
use Folklore\Image\Facade as ImageFacade;
use Illuminate\Support\Arr;

class ImageSize implements ImageSizeContract
{
    protected $image;

    protected $size;

    protected $filters;

    protected $format;

    protected $url;

    protected $dimension;

    public function __construct(ImageContract $image, $size, $format = null, $filters = null)
    {
        $this->image = $image;
        $this->size = Arr::except($size, ['format']);
        $this->format = $format ?? data_get($size, 'format');
        $this->filters = $filters;
    }

    public function id(): string
    {
        return $this->size['id'];
    }

    public function url(): string
    {
        if (!isset($this->url)) {
            $imageUrl = $this->image->url();
            $metadata = $this->image->metadata();
            $mime = !is_null($metadata) ? $metadata->mime() : null;
            $isSVG = $mime === 'image/svg' || $mime === 'image/svg+xml';
            $path = parse_url($imageUrl, PHP_URL_PATH);
            $filters = $this->filters ?? [];
            if ($this->size['id'] !== 'original' && !$isSVG) {
                $filters[] = $this->size['id'];
            }
            if (
                isset($this->format) &&
                !$isSVG &&
                preg_match('/\.' . preg_quote($this->format, '/') . '$/', $imageUrl) === 0
            ) {
                $filters['format'] = $this->format;
            }
            $this->url = sizeof($filters)
                ? rtrim(config('app.url'), '/') . ImageFacade::url($path, $filters)
                : $imageUrl;
        }
        return $this->url;
    }

    public function width(): int
    {
        if (!isset($this->dimension)) {
            $this->dimension = $this->getDimension();
        }
        return data_get($this->dimension, 'width', 0);
    }

    public function height(): int
    {
        if (!isset($this->dimension)) {
            $this->dimension = $this->getDimension();
        }
        return data_get($this->dimension, 'height', 0);
    }

    public function mime(): ?string
    {
        switch ($this->format) {
            case 'gif':
                return 'image/gif';
                break;
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
                break;
            case 'png':
                return 'image/png';
            case 'webp':
                return 'image/webp';
                break;
            case 'avif':
                return 'image/avif';
                break;
        }
        $metadata = $this->image->metadata();
        return !is_null($metadata) ? $metadata->mime() : null;
    }

    protected function getDimension()
    {
        $metadata = $this->image->metadata();
        $imageWidth = $metadata->width();
        $imageHeight = $metadata->height();
        $filterWidth = data_get($this->size, 'maxWidth');
        $filterHeight = data_get($this->size, 'maxHeight');

        $dimension = [
            'width' => data_get($this->size, 'width', $imageWidth),
            'height' => data_get($this->size, 'height', $imageHeight),
        ];

        if (isset($filterWidth) && isset($filterHeight)) {
            $maxWidth = min($filterWidth, $imageWidth);
            $maxHeight = min($filterHeight, $imageHeight);
            $widthScale = $imageWidth > 0 ? $maxWidth / $imageWidth : 0;
            $heightScale = $imageHeight > 0 ? $maxHeight / $imageHeight : 0;
            $scale = min($widthScale, $heightScale);
            $dimension['width'] = (int) round($imageWidth * $scale);
            $dimension['height'] = (int) round($imageHeight * $scale);
        } elseif (isset($filterWidth)) {
            $maxWidth = min($filterWidth, $imageWidth);
            $scale = $maxWidth / $imageWidth;
            $dimension['width'] = $filterWidth;
            $dimension['height'] = (int) round($imageHeight * $scale);
        } elseif (isset($filterHeight)) {
            $maxHeight = min($filterHeight, $imageHeight);
            $scale = $maxHeight / $imageHeight;
            $dimension['height'] = $filterHeight;
            $dimension['width'] = (int) round($imageWidth * $scale);
        }

        return $dimension;
    }
}
