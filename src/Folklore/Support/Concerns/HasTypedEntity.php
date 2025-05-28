<?php

namespace Folklore\Support\Concerns;

use Folklore\Contracts\Entities\Entity;

trait HasTypedEntity
{
    // protected $entitiesByType = [];

    // protected $entityTypeColumn = 'type';

    public function toTypedEntity(): ?Entity
    {
        $column = isset($this->entityTypeColumn) ? $this->entityTypeColumn : 'type';
        $type = $this->{$column};
        $entity = !empty($type)
            ? data_get($this->entitiesByType, $type, null)
            : null;
        return isset($entity) ? new $entity($this) : null;
    }
}
