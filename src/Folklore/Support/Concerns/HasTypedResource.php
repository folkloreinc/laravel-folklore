<?php

namespace Folklore\Support\Concerns;

use Folklore\Contracts\Resources\Resource;

trait HasTypedResource
{
    // protected $typedResources = [];

    // protected $typedResourceColumn = 'type';

    public function toTypedResource(): ?Resource
    {
        $column = isset($this->typedResourceColumn) ? $this->typedResourceColumn : 'type';
        $resource = data_get($this->typedResources, $this->{$column}, null);
        return isset($resource) ? new $resource($this) : null;
    }
}
