<?php

namespace Folklore\Contracts\Entities;

/**
 * A model whose entity's public id is not its key: it says which column holds it, as
 * `getRouteKeyName()` says which one its routes use. Repositories find by it, and
 * `ModelEntity::id()` returns it (docs/decisions/0015). A model that doesn't implement
 * it is found by its key.
 */
interface HasEntityKey
{
    /**
     * The column that holds the entity's public id.
     */
    public function getEntityKeyName(): string;

    /**
     * The value of that column.
     */
    public function getEntityKey(): mixed;
}
