<?php

namespace Folklore\Support\Concerns;

use Folklore\Contracts\Resources\Resource;

trait HasTypedResource
{
    protected $typedResources = [];
    protected $typedResourceColumn = 'type';

    public function toTypedResource(): Resource
    {
        $resource = data_get($this->typedResources, $this->{$this->typedResourceColumn}, 'default');
        return new $resource($this);
    }
}
