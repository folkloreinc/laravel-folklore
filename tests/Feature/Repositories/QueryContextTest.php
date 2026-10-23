<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Contracts\Repositories\Entities;
use Folklore\Contracts\Repositories\QueryContextResolver;
use Folklore\Repositories\Query\EntitiesQuery;
use Folklore\Repositories\Query\Sort;
use Folklore\Tests\Feature\Repositories\Fixtures\Note;
use Folklore\Tests\Feature\Repositories\Fixtures\NoteEntity;
use Folklore\Tests\Feature\Repositories\Fixtures\Notes;

/**
 * The query context of the base repository and its typed query (docs/decisions/0004, 0015,
 * 0017).
 */
class QueryContextTest extends NotesTestCase
{
    private function note(string $title, string $workspace, bool $published = true): Note
    {
        return Note::create(['title' => $title, 'workspace' => $workspace, 'published' => $published]);
    }

    /**
     * @return list<string|null>
     */
    private function titles(iterable $entities): array
    {
        $titles = [];
        foreach ($entities as $entity) {
            $this->assertInstanceOf(NoteEntity::class, $entity);
            $titles[] = $entity->title();
        }

        return $titles;
    }

    public function test_with_global_query_returns_a_scoped_copy()
    {
        $this->note('Ours', 'a');
        $this->note('Theirs', 'b');
        $notes = new Notes;

        $scoped = $notes->withGlobalQuery(['workspace' => 'a']);

        $this->assertSame(['Ours'], $this->titles($scoped->get()));
        $this->assertCount(2, $notes->get());
    }

    public function test_finds_apply_the_context()
    {
        $theirs = $this->note('Theirs', 'b');
        $scoped = (new Notes)->withGlobalQuery(['workspace' => 'a']);

        $this->assertNull($scoped->findById((string) $theirs->id));
        $this->assertNull($scoped->update((string) $theirs->id, ['title' => 'Changed']));
        $this->assertFalse($scoped->destroy((string) $theirs->id));
        $this->assertNull($scoped->valueById($theirs->id, 'title'));
        $this->assertSame('Theirs', $theirs->fresh()?->getAttribute('title'));
        $this->assertNotNull((new Notes)->findById((string) $theirs->id));
    }

    public function test_finds_ignore_the_default_params()
    {
        $draft = $this->note('Draft', 'a', published: false);

        $this->assertSame([], $this->titles((new Notes)->get()));
        $this->assertNotNull((new Notes)->findById((string) $draft->id));
    }

    public function test_the_default_resolver_applies_to_every_repository()
    {
        $this->app->instance(QueryContextResolver::class, new class implements QueryContextResolver
        {
            public function resolve(Entities $repository): array
            {
                return ['workspace' => 'a'];
            }
        });
        $this->note('Ours', 'a');
        $theirs = $this->note('Theirs', 'b');

        $this->assertSame(['Ours'], $this->titles((new Notes)->get()));
        $this->assertNull((new Notes)->findById((string) $theirs->id));
        $this->assertSame(['Theirs'], $this->titles((new Notes)->withGlobalQuery(['workspace' => 'b'])->get()));
        $this->assertSame(['Theirs'], $this->titles((new Notes)->get(['workspace' => 'b'])));
    }

    public function test_set_global_query_still_changes_the_repository()
    {
        $this->note('Ours', 'a');
        $this->note('Theirs', 'b');
        $notes = new Notes;

        $notes->setGlobalQuery(['workspace' => 'b']);

        $this->assertSame(['Theirs'], $this->titles($notes->get()));
    }

    public function test_the_typed_query_filters_searches_sorts_and_pages()
    {
        foreach (['Banana', 'Apple', 'Cherry', 'Apricot'] as $title) {
            $this->note($title, 'a');
        }
        $this->note('Avocado', 'b');
        $notes = (new Notes)->withGlobalQuery(['workspace' => 'a']);

        $page = $notes->queryEntities(new EntitiesQuery(search: 'A', sorts: [Sort::descending('title')], perPage: 2));

        $this->assertSame(['Banana', 'Apricot'], $this->titles($page->items()));
        $this->assertSame(3, $page->total());
        $this->assertSame(['Apricot', 'Apple'], $this->titles($notes->queryEntities((new EntitiesQuery(sorts: [Sort::descending('title')], perPage: 2))->withPage(2))->items()));
        $this->assertSame(['Apple', 'Apricot'], $this->titles($notes->queryEntities(new EntitiesQuery(filters: ['workspace' => 'a'], search: 'Ap', sorts: [Sort::ascending('title')]))->items()));
    }

    public function test_the_typed_query_finds_by_ids_and_exclusions()
    {
        $first = $this->note('First', 'a');
        $second = $this->note('Second', 'a');
        $this->note('Third', 'a');
        $notes = new Notes;

        $this->assertSame(['First', 'Second'], $this->titles($notes->queryEntities(new EntitiesQuery(ids: [(string) $first->id, (string) $second->id], sorts: [Sort::ascending('title')]))->items()));
        $this->assertSame([], $this->titles($notes->queryEntities(new EntitiesQuery(ids: []))->items()));
        $this->assertSame(['Third'], $this->titles($notes->queryEntities(new EntitiesQuery(except: [(string) $first->id, (string) $second->id]))->items()));
    }
}
