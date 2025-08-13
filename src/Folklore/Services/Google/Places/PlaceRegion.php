<?php

namespace App\Services\Google\Places;

use Folklore\Contracts\Services\Google\Places\Bounds as BoundsContract;
use Folklore\Contracts\Services\Google\Places\Region;
use Folklore\Contracts\Services\Google\Places;

use Carbon\Carbon;
use Google\Service\MapsPlaces\GoogleMapsPlacesV1PlaceAddressComponent;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlaceRegion implements Arrayable, Region
{
    protected $place;

    protected $component;

    protected $parent;

    protected $images;

    protected $uris;

    protected $bounds;

    public function __construct(
        GoogleMapsPlacesV1PlaceAddressComponent $component,
        ?Region $parent = null
    ) {
        $this->component = $component;
        $this->parent = $parent;
    }

    public function id(): string
    {
        return $this->handle();
    }

    public function type(): string
    {
        return 'region';
    }

    public function slug(): string
    {
        return Str::slug($this->label());
    }

    public function parent(): ?Region
    {
        return $this->parent;
    }

    public function level(): ?string
    {
        $types = $this->component->getTypes();
        if (in_array('administrative_area_level_1', $types)) {
            return Region::LEVEL1;
        }
        if (in_array('administrative_area_level_2', $types)) {
            return Region::LEVEL2;
        }
        if (in_array('administrative_area_level_3', $types)) {
            return Region::LEVEL3;
        }

        return null;
    }

    public function bounds(): ?BoundsContract
    {
        if (!isset($this->bounds)) {
            $this->bounds = resolve(Places::class)->findRegionBoundsByName(
                $this->component->getLongText()
            );
        }

        return $this->bounds;
    }

    public function handle(): string
    {
        return Str::slug('level' . ($this->level() ?? '') . '-' . $this->label());
    }

    public function label(): string
    {
        return $this->component->getLongText();
    }

    public function image()
    {
        return null;
    }

    public function images(): Collection
    {
        return collect();
    }

    public function metadata()
    {
        return null;
    }

    public function uris(): Collection
    {
        if (!isset($this->uris)) {
            $this->uris = collect([
                [
                    'type' => 'slug',
                    'uri' => Str::slug($this->label()),
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

    public function source(): string
    {
        return 'google_places';
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

    public function toArray()
    {
        $bounds = $this->bounds();

        return [
            'type' => $this->type(),
            'handle' => $this->handle(),
            'label' => $this->label(),
            'level' => $this->level(),
            'parent' => $this->parent(),
            'bounds' => $bounds instanceof Arrayable ? $bounds->toArray() : $bounds,
            'image' => $this->image(),
            'images' => $this->images()->toArray(),
            'uris' => $this->uris()->toArray(),
        ];
    }
}
