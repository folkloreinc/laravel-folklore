<?php

namespace Folklore\Tests\Feature\Repositories\Fixtures;

use Folklore\Contracts\Entities\ToEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A note, found by its id.
 *
 * @implements ToEntity<NoteEntity>
 */
class Note extends Model implements ToEntity
{
    protected $table = 'notes';

    protected $fillable = ['title', 'workspace', 'published'];

    protected static function booted(): void
    {
        static::creating(function (Note $note) {
            $note->setAttribute('uid', $note->getAttribute('uid') ?? 'nte_'.Str::lower((string) Str::ulid()));
        });
    }

    public function toEntity(): NoteEntity
    {
        return new NoteEntity($this);
    }
}
