<?php

namespace Folklore\Contracts\Entities;

/**
 * A model that turns into its entity.
 *
 * @template TEntity of Entity = Entity
 */
interface ToEntity
{
    /**
     * @return TEntity|null
     */
    public function toEntity(): ?Entity;
}
