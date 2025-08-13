<?php

namespace Folklore\Services\Google;

use Folklore\Contracts\Services\Google\Places\Location as LocationContract;
use Folklore\Contracts\Services\Google\Places\Bounds as BoundsContract;
use Folklore\Contracts\Services\Google\Places\Region as RegionContract;
use Folklore\Contracts\Services\Google\Places as ServicesPlacesContract;

use Folklore\Services\Google\Places\Place;
use Folklore\Services\Google\Places\PlaceRegion;
use Folklore\Services\Google\Places\Bounds;

use Google\Client as GoogleClient;
use Google\Service\MapsPlaces as PlacesService;
use Google\Service\MapsPlaces\GoogleMapsPlacesV1SearchTextRequest;
use Google\Service\MapsPlaces\GoogleMapsPlacesV1SearchTextResponse;

use GuzzleHttp\Client as HttpClient;

class Places implements ServicesPlacesContract
{
    protected $client;

    protected $service;

    public function __construct($key)
    {
        $httpClient = new HttpClient([
            'headers' => [
                'referer' => config('app.url'),
            ],
        ]);

        $this->client = new GoogleClient();
        $this->client->setHttpClient($httpClient);
        $this->client->setApplicationName(config('app.name'));
        $this->client->setDeveloperKey($key);

        $this->service = new PlacesService($this->client);
    }

    public function findLocationById(string $id, ?array $options = null): ?LocationContract
    {
        $place = $this->service->places->get(
            'places/' . $id,
            array_merge(
                [
                    'languageCode' => 'fr-CA',
                    'regionCode' => 'CA',
                    'fields' => 'id,name,displayName,addressComponents,location',
                ],
                $options ?? []
            )
        );
        return isset($place) ? new Place($place) : null;
    }

    public function findLocationByName(string $name, ?array $options = null): ?LocationContract
    {
        $request = new GoogleMapsPlacesV1SearchTextRequest(
            array_merge(
                [
                    'textQuery' => $name,
                    'languageCode' => 'fr-CA',
                    'regionCode' => 'CA',
                    // 'strictTypeFiltering' => true
                ],
                $options ?? []
            )
        );
        $places = $this->service->places->searchText($request, [
            'fields' =>
                'places.id,places.name,places.displayName,places.addressComponents,places.location,places.types',
        ]);
        $places =
            $places instanceof GoogleMapsPlacesV1SearchTextResponse
                ? $places->getPlaces()
                : $places;
        $place = count($places) > 0 ? $places[0] : null;
        return isset($place) ? new Place($place) : null;
    }

    public function findRegionByName(string $name, ?array $options = null): ?RegionContract
    {
        $request = new GoogleMapsPlacesV1SearchTextRequest(
            array_merge(
                [
                    'textQuery' => $name,
                    'languageCode' => 'fr-CA',
                    'regionCode' => 'CA',
                    'includedType' => 'administrative_area_level_2',
                    // 'strictTypeFiltering' => true
                ],
                $options ?? []
            )
        );
        $places = $this->service->places->searchText($request, [
            'fields' => 'places.id,places.name,places.addressComponents',
        ]);
        $places =
            $places instanceof GoogleMapsPlacesV1SearchTextResponse
                ? $places->getPlaces()
                : $places;
        $place = count($places) > 0 ? $places[0] : null;
        if (!isset($place)) {
            return null;
        }
        $region = collect($place->getAddressComponents())->first(function ($component) {
            return in_array('administrative_area_level_2', $component->getTypes());
        });

        return isset($region) ? new PlaceRegion($region) : null;
    }

    public function findRegionBoundsByName(string $name, ?array $options = null): ?BoundsContract
    {
        $request = new GoogleMapsPlacesV1SearchTextRequest(
            array_merge(
                [
                    'textQuery' => $name,
                    'languageCode' => 'fr-CA',
                    'regionCode' => 'CA',
                    'includedType' => 'administrative_area_level_2',
                ],
                $options
            )
        );
        $places = $this->service->places->searchText($request, [
            'fields' => 'places.viewport',
        ]);
        $places =
            $places instanceof GoogleMapsPlacesV1SearchTextResponse
                ? $places->getPlaces()
                : $places;
        $place = count($places) > 0 ? $places[0] : null;
        $viewport = isset($place) ? $place->getViewport() : null;

        return isset($viewport) ? new Bounds($viewport) : null;
    }
}
