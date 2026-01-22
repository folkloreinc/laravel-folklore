<?php

namespace Folklore\Services\Google\Maps;

use Folklore\Contracts\Services\Google\Maps\Position as PositionContract;

use Illuminate\Contracts\Support\Arrayable;

class Position implements Arrayable, PositionContract
{
    public function __construct(protected array $data)
    {
    }

    public function latitude(): float
    {
        return $this->data['lat'];
    }

    public function longitude(): float
    {
        return $this->data['lng'];
    }

    public function toArray()
    {
        return [
            'latitude' => $this->latitude(),
            'longitude' => $this->longitude(),
        ];
    }
}
