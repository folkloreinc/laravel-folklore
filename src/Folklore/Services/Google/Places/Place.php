<?php

namespace Folklore\Services\Google\Places;

use Folklore\Contracts\Services\Google\Places\Location;
use Folklore\Contracts\Services\Google\Places\LocationMetadata as LocationMetadataContract;

use Carbon\Carbon;
use Google\Service\MapsPlaces\GoogleMapsPlacesV1Place;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Place implements Arrayable, Location
{
    protected $place;

    protected $uris;

    protected $metadata;

    public function __construct(GoogleMapsPlacesV1Place $place)
    {
        $this->place = $place;
    }

    public function id(): string
    {
        return $this->place->getId();
    }

    public function handle(): string
    {
        return Str::slug($this->name());
    }

    public function slug(): string
    {
        return Str::slug($this->name());
    }

    public function name(): string
    {
        return $this->place->getDisplayName()->getText();
    }

    public function address(): ?string
    {
        $streetNumber = $this->getAddressComponent('street_number');
        $route = $this->getAddressComponent('route');
        return collect([$streetNumber, $route])
            ->filter(function ($component) {
                return isset($component);
            })
            ->map(function ($component) {
                return $component->getLongText();
            })
            ->join(' ');
    }

    public function city(): ?string
    {
        $component = $this->getAddressComponent('locality');
        return isset($component) ? $component->getLongText() : null;
    }

    public function postalCode(): ?string
    {
        $component = $this->getAddressComponent('postal_code');
        return isset($component) ? $component->getLongText() : null;
    }

    public function region(): ?string
    {
        $component = $this->getAddressComponent('administrative_area_level_1');
        return isset($component) ? $component->getLongText() : null;
    }

    public function country(): ?string
    {
        $component = $this->getAddressComponent('country');
        return isset($component) ? $component->getShortText() : null;
    }

    public function latitude(): ?float
    {
        $location = $this->place->getLocation();
        return isset($location) ? $location->getLatitude() : null;
    }

    public function longitude(): ?float
    {
        $location = $this->place->getLocation();
        return isset($location) ? $location->getLongitude() : null;
    }

    public function image()
    {
        return null;
    }

    public function images(): Collection
    {
        return collect();
    }

    public function source(): string
    {
        return 'google_places';
    }

    public function uris(): Collection
    {
        if (!isset($this->uris)) {
            $this->uris = collect([
                [
                    'type' => 'slug',
                    'uri' => Str::slug($this->name()),
                ],
            ])
                ->filter(function ($item) {
                    return !empty($item['uri']);
                })
                ->unique(function ($item) {
                    return $item['type'] . '_' . $item['uri'];
                })
                ->values();
        }

        return $this->uris;
    }

    public function metadata(): LocationMetadataContract
    {
        if (!isset($this->metadata)) {
            $this->metadata = new PlaceMetadata($this->place, $this);
        }
        return $this->metadata;
    }

    public function toArray()
    {
        $metadata = $this->metadata();

        return [
            'handle' => $this->handle(),
            'name' => $this->name(),
            'address' => $this->address(),
            'city' => $this->city(),
            'region' => $this->region(),
            'postalcode' => $this->postalCode(),
            'country' => $this->country(),
            'latitude' => $this->latitude(),
            'longitude' => $this->longitude(),
            'image' => $this->image(),
            'images' => $this->images()->toArray(),
            'uris' => $this->uris()->toArray(),
            'metadata' => $metadata instanceof Arrayable ? $metadata->toArray() : $metadata,
        ];
    }

    public function sourceId(): ?string
    {
        return $this->id();
    }

    public function sourceUpdatedAt(): ?Carbon
    {
        return null;
    }

    public function sourceData(): ?array
    {
        return null;
    }

    public function getAddressComponent($type)
    {
        return collect($this->place->getAddressComponents())->first(function ($component) use (
            $type
        ) {
            return !is_null($component) &&
                !is_null($component->getTypes()) &&
                in_array($type, $component->getTypes());
        });
    }
}
