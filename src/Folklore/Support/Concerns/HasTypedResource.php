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
        $type = $this->{$column};
        $resource = !empty($type) ? data_get($this->typedResources, $type, null) : null;
        return isset($resource) ? new $resource($this) : null;
    }
}
