<?php

namespace Folklore\Services\Google\Places;

use Folklore\Contracts\Services\Google\Places\Bounds as BoundsContract;

use Google\Service\MapsPlaces\GoogleGeoTypeViewport;
use Illuminate\Contracts\Support\Arrayable;

class Bounds implements Arrayable, BoundsContract
{
    protected $viewport;

    public function __construct(GoogleGeoTypeViewport $viewport)
    {
        $this->viewport = $viewport;
    }

    public function northeastLatitude(): float
    {
        return $this->viewport->getHigh()->getLatitude();
    }

    public function northeastLongitude(): float
    {
        return $this->viewport->getHigh()->getLongitude();
    }

    public function southwestLatitude(): float
    {
        return $this->viewport->getLow()->getLatitude();
    }

    public function southwestLongitude(): float
    {
        return $this->viewport->getLow()->getLongitude();
    }

    public function toArray()
    {
        return [
            'northeast_latitude' => $this->northeastLatitude(),
            'northeast_longitude' => $this->northeastLongitude(),
            'southwest_latitude' => $this->southwestLatitude(),
            'southwest_longitude' => $this->southwestLongitude(),
        ];
    }
}
