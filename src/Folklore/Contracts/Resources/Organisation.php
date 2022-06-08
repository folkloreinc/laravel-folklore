<?php

namespace Folklore\Contracts\Resources;

interface Organisation extends Resource
{
    public function name(): string;

    public function slug(): string;
}
