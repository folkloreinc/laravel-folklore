<?php

namespace Folklore\Tests\Feature\Repositories\Fixtures;

use Folklore\Contracts\Repositories\Entities;

/**
 * A repository contract typed on its entity, as an application writes one: the base
 * repository passes its `TEntity` to `Entities`, so `Notes` can implement both.
 *
 * @extends Entities<NoteEntity>
 */
interface NotesContract extends Entities
{
    public function findById(string $id): ?NoteEntity;
}
