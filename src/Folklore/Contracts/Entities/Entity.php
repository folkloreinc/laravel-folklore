<?php

namespace Folklore\Contracts\Entities;

/**
 * An object that business code manipulates instead of a model.
 */
interface Entity
{
    /**
     * The public id of the entity.
     */
    public function id(): string;
}
