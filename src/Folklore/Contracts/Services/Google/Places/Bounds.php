<?php

namespace Folklore\Contracts\Services\Google\Places;

interface Bounds
{
    public function northeastLatitude(): float;

    public function northeastLongitude(): float;

    public function southwestLatitude(): float;

    public function southwestLongitude(): float;
}
