<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\Image as ImageContract;
use Folklore\Contracts\Resources\ImageSize as ImageSizeContract;
use Folklore\Image\Facade as ImageFacade;

class ImageSize implements ImageSizeContract
{
    protected $image;

    protected $filter;

    protected $url;

    protected $dimension;

    public function __construct(ImageContract $image, $filter)
    {
        $this->image = $image;
        $this->filter = $filter;
    }

    public function id(): string
    {
        return $this->filter['id'];
    }

    public function name(): string
    {
        return $this->id();
    }

    public function url(): string
    {
        $image = $this->image;
        $imageUrl = $image->url();
        $metadata = $image->metadata();
        $mime = !is_null($metadata) ? $metadata->mime() : null;
        $isSVG = $mime === 'image/svg';
        if (!isset($this->url)) {
            $path = parse_url($imageUrl, PHP_URL_PATH);
            $this->url =
                $this->filter['id'] !== 'original' && !$isSVG
                    ? rtrim(config('app.url'), '/') . ImageFacade::url($path, [$this->filter['id']])
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
