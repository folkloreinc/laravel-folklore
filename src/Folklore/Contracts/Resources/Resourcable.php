<?php

namespace Folklore\Contracts\Resources;

interface Resourcable
{
    public function toResource(): Resource;
}
