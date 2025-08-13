<?php

namespace Folklore\Contracts\Services\Google;

use Folklore\Contracts\Services\Google\Places\Region;
use Folklore\Contracts\Services\Google\Places\Bounds;

interface Places
{
    public function findRegionByName(string $name): ?Region;

    public function findRegionBoundsByName(string $name): ?Bounds;
}
