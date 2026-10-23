<?php

namespace Folklore\Tests\Feature\Repositories\Fixtures;

use Folklore\Entities\ModelEntity;

/**
 * @extends ModelEntity<Note>
 */
class NoteEntity extends ModelEntity
{
    public function title(): ?string
    {
        $title = $this->model->getAttribute('title');

        return is_string($title) ? $title : null;
    }
}
