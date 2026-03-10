<?php

namespace Folklore\Contracts\Services\Google\Maps;

interface Position
{
    public function latitude(): float;

    public function longitude(): float;
}
