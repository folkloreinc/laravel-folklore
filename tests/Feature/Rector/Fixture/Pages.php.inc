<?php

namespace App\Repositories;

use Folklore\Repositories\Entities;

class Pages extends Entities
{
    protected $jsonAttributeName = 'content';

    protected function jsonColumn(): string
    {
        return $this->jsonAttributeName;
    }
}
