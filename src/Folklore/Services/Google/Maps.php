<?php

namespace Folklore\Services\Google;

use Folklore\Contracts\Services\Google\Maps as ServicesMapsContract;
use Folklore\Contracts\Services\Google\Maps\Position as PositionContract;
use Folklore\Services\Google\Maps\Position;
use Folklore\Support\Concerns\MakesRequests;

class Maps implements ServicesMapsContract
{
    use MakesRequests;

    public function __construct(protected string $key)
    {
    }

    public function findPositionFromAddress(string $address): ?PositionContract
    {
        $response = $this->requestJson('https://maps.googleapis.com/maps/api/geocode/json', 'GET', [
            'address' => $address,
            'key' => $this->key,
        ]);
        $position = data_get($response, 'results.0.geometry.location');
        return isset($position) ? new Position($position) : null;
    }

    public function findTimezoneFromPosition(float $latitude, float $longitude): ?string
    {
        $response = $this->requestJson(
            'https://maps.googleapis.com/maps/api/timezone/json',
            'GET',
            [
                'location' => $latitude . ',' . $longitude,
                'timestamp' => time(),
                'key' => $this->key,
            ]
        );
        return data_get($response, 'timeZoneId');
    }
}
