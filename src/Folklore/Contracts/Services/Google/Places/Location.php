<?php

namespace Folklore\Contracts\Services\Google\Places;

interface Location
{
    public function metadata(): LocationMetadata;
}
