<?php

namespace Folklore\Tests\Feature\Repositories\Fixtures;

use Folklore\Contracts\Entities\HasEntityKey;

/**
 * A note whose public id is its uid, as the pilot's models are.
 */
class NoteWithUid extends Note implements HasEntityKey
{
    public function getEntityKeyName(): string
    {
        return 'uid';
    }

    public function getEntityKey(): mixed
    {
        return $this->getAttribute($this->getEntityKeyName());
    }
}
