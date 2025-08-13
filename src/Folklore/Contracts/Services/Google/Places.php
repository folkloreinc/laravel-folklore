<?php

namespace Folklore\Contracts\Services\Google;

use Folklore\Contracts\Services\Google\Places\Region;
use Folklore\Contracts\Services\Google\Places\Bounds;
use Folklore\Contracts\Services\Google\Places\Location;

interface Places
{
    public function findPlaceById(string $id): ?Location;

    public function findPlaceByName(string $name): ?Location;

    public function findRegionByName(string $name): ?Region;

    public function findRegionBoundsByName(string $name): ?Bounds;
}
