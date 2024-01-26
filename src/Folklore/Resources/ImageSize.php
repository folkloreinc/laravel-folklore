<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\Image as ImageContract;
use Folklore\Contracts\Resources\ImageSize as ImageSizeContract;
use Folklore\Image\Facade as ImageFacade;
use Illuminate\Support\Arr;

class ImageSize implements ImageSizeContract
{
    protected $image;

    protected $filter;

    protected $format;

    protected $url;

    protected $dimension;

    public function __construct(ImageContract $image, $filter, $format = null)
    {
        $this->image = $image;
        $this->filter = Arr::except($filter, ['format']);
        $this->format = $format ?? data_get($filter, 'format');
    }

    public function id(): string
    {
        return $this->filter['id'];
    }

    public function url(): string
    {
        if (!isset($this->url)) {
            $imageUrl = $this->image->url();
            $metadata = $this->image->metadata();
            $mime = !is_null($metadata) ? $metadata->mime() : null;
            $isSVG = $mime === 'image/svg';
            $path = parse_url($imageUrl, PHP_URL_PATH);
            $filters = [];
            if ($this->filter['id'] !== 'original' && !$isSVG) {
                $filters[] = $this->filter['id'];
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
        }
        $metadata = $this->image->metadata();
        return !is_null($metadata) ? $metadata->mime() : null;
    }

    protected function getDimension()
    {
        $metadata = $this->image->metadata();
        $imageWidth = $metadata->width();
        $imageHeight = $metadata->height();
        $filterWidth = data_get($this->filter, 'maxWidth');
        $filterHeight = data_get($this->filter, 'maxHeight');

        $dimension = [
            'width' => data_get($this->filter, 'width', $imageWidth),
            'height' => data_get($this->filter, 'height', $imageHeight),
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
