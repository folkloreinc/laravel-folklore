<?php

namespace Folklore\Contracts\Services\Google;

use Folklore\Contracts\Services\Google\Maps\Position;

interface Maps
{
    public function findPositionFromAddress(string $address): ?Position;

    public function findTimezoneFromPosition(float $latitude, float $longitude): ?string;
}
