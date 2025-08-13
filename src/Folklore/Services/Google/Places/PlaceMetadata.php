<?php

namespace Folklore\Services\Google\Places;

use Folklore\Contracts\Services\Google\Places\LocationMetadata as LocationMetadataContract;
use Google\Service\MapsPlaces\GoogleMapsPlacesV1Place;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class PlaceMetadata implements Arrayable, LocationMetadataContract
{
    protected $data;

    protected $place;

    protected $types;

    public function __construct(GoogleMapsPlacesV1Place $data, Place $place)
    {
        $this->data = $data;
        $this->place = $place;
    }

    public function categories(): ?Collection
    {
        return null;
    }

    public function types(): ?Collection
    {
        return null;
    }

    public function regions(): ?Collection
    {
        return collect([
            $this->place->getAddressComponent('administrative_area_level_1'),
            $this->place->getAddressComponent('administrative_area_level_2'),
            $this->place->getAddressComponent('administrative_area_level_3'),
        ])
            ->filter(function ($region) {
                return isset($region) && in_array('political', $region->getTypes());
            })
            ->values()
            ->reduce(function ($newRegions, $region, $index) {
                $parent = $index > 0 ? $newRegions->last() : null;

                return $newRegions->push(new PlaceRegion($region, $parent));
            }, collect());
    }

    public function toArray()
    {
        return [
            'regions' => $this->regions(),
        ];
    }
}
