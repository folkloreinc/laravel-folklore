<?php

namespace Folklore\Contracts\Services\Google;

use Folklore\Contracts\Services\Google\Places\Region;
use Folklore\Contracts\Services\Google\Places\Bounds;
use Folklore\Contracts\Services\Google\Places\Location;

interface Places
{
    public function findLocationById(string $id): ?Location;

    public function findLocationByName(string $name): ?Location;

    public function findRegionByName(string $name): ?Region;

    public function findRegionBoundsByName(string $name): ?Bounds;
}
