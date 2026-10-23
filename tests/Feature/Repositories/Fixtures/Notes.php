<?php

namespace Folklore\Tests\Feature\Repositories\Fixtures;

use Folklore\Repositories\Entities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Notes, filtered by workspace and published state, searched by title.
 *
 * @extends Entities<Note, NoteEntity>
 */
class Notes extends Entities implements NotesContract
{
    /**
     * @var list<string>
     */
    protected $queryColumns = ['workspace', 'published'];

    public static bool $byUid = false;

    public function findById(string $id): ?NoteEntity
    {
        return parent::findById($id);
    }

    protected function newModel(): Model
    {
        return static::$byUid ? new NoteWithUid : new Note;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultParams(): array
    {
        return ['published' => true];
    }

    /**
     * @param  Builder<Note>  $query
     * @param  array<string, mixed>  $params
     * @return Builder<Note>
     */
    protected function buildQueryFromParams($query, $params): Builder
    {
        $query = parent::buildQueryFromParams($query, $params);
        if (! $query instanceof Builder) {
            throw new LogicException('An Eloquent query was expected.');
        }
        if (isset($params['search']) && is_string($params['search'])) {
            $query->whereLike('title', '%'.$params['search'].'%');
        }

        return $query;
    }
}
