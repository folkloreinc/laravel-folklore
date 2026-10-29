<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Repositories\Entities as EntitiesContract;
use Folklore\Contracts\Repositories\QueryContextResolver;
use Folklore\Panneau\EntitiesRepository;
use Folklore\Panneau\SearchableEntitiesRepository;
use Folklore\Tests\Feature\Repositories\Fixtures\Article;
use Folklore\Tests\Feature\Repositories\Fixtures\ArticleEntity;
use Folklore\Tests\Feature\Repositories\Fixtures\Articles;
use Folklore\Tests\Feature\Repositories\Fixtures\ArticlesContract;
use Folklore\Tests\Feature\Repositories\Fixtures\Notes;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use LogicException;
use Panneau\Contracts\Repositories\ChecksSlugs;
use Panneau\Contracts\Repositories\Searchable;
use Panneau\Query\QueryOptions;
use Panneau\Query\ResourceQuery;
use Panneau\Query\Sort;
use Panneau\Query\SortDirection;
use Panneau\Repositories\RepositoryAdapters;
use Panneau\Support\Facade;
use stdClass;

/**
 * The adapter of a Folklore repository for Panneau 5 (docs/decisions/0018).
 */
class EntitiesRepositoryTest extends PanneauTestCase
{
    private const SCOPE = ['workspace' => 'folklore'];

    private function adapter(): EntitiesRepository
    {
        $adapter = $this->app->make(RepositoryAdapters::class)->resolve(ArticlesContract::class);
        $this->assertInstanceOf(EntitiesRepository::class, $adapter);

        return $adapter;
    }

    /**
     * @param  array<array-key, mixed>  $parameters  The list's query string.
     * @return list<string|null>
     */
    private function titles(array $parameters = [], array $scope = self::SCOPE): array
    {
        $options = new QueryOptions(
            filters: ['status', 'published_at', 'workspace'],
            sorts: ['title', 'published_at'],
            searchFields: ['title'],
        );
        $page = $this->adapter()->query(ResourceQuery::fromParameters($parameters, $options, $scope));

        return array_map(fn (ArticleEntity $article) => $article->title(), $page->items());
    }

    public function test_the_module_adapts_the_repositories_with_the_typed_query()
    {
        $adapter = $this->adapter();

        $this->assertInstanceOf(SearchableEntitiesRepository::class, $adapter);
        $this->assertInstanceOf(Articles::class, $adapter->repository());
        $this->assertInstanceOf(Searchable::class, $adapter);
        $this->assertInstanceOf(ChecksSlugs::class, $adapter);
    }

    public function test_a_repository_that_does_not_search_has_no_search()
    {
        $adapter = $this->app->make(RepositoryAdapters::class)->resolve(new Notes);

        $this->assertInstanceOf(EntitiesRepository::class, $adapter);
        $this->assertNotInstanceOf(Searchable::class, $adapter);
    }

    public function test_a_list_of_a_repository_that_does_not_search_ignores_the_search()
    {
        $this->article(['title' => 'Montreal']);
        $this->article(['title' => 'Quebec']);
        $adapter = new EntitiesRepository(new Articles);

        $page = $adapter->query(new ResourceQuery(search: 'mont', searchFields: ['title'], scope: self::SCOPE));

        $this->assertSame(2, $page->total());
    }

    public function test_only_the_repositories_with_the_typed_query_are_adapted()
    {
        $adapters = $this->app->make(RepositoryAdapters::class);

        $this->assertTrue($adapters->supports(new Notes));
        $this->assertFalse($adapters->supports(new stdClass));
    }

    public function test_a_site_adapter_registered_after_the_module_takes_precedence()
    {
        $adapter = new EntitiesRepository(new Articles);
        Facade::repositoryAdapter(ArticlesContract::class, fn () => $adapter);

        $this->assertSame($adapter, $this->app->make(RepositoryAdapters::class)->resolve(ArticlesContract::class));
    }

    public function test_a_list_is_a_page_of_the_entities_within_the_scope()
    {
        $this->article(['title' => 'B']);
        $this->article(['title' => 'A']);
        $this->article(['title' => 'C', 'workspace' => 'urbania']);

        $this->assertSame(['A', 'B'], $this->titles(['sort' => 'title']));
        $this->assertSame(['B'], $this->titles(['sort' => '-title', 'perPage' => '1']));
        $this->assertSame(['A'], $this->titles(['sort' => '-title', 'perPage' => '1', 'page' => '2']));
        $this->assertSame(['C'], $this->titles([], ['workspace' => 'urbania']));
        $this->assertSame(['A', 'B', 'C'], $this->titles(['sort' => 'title'], []));
    }

    public function test_a_list_applies_the_filters_as_the_repository_params()
    {
        $this->article(['title' => 'Draft', 'status' => 'draft']);
        $this->article(['title' => 'Old', 'published_at' => '2020-01-15 10:00:00']);
        $this->article(['title' => 'New', 'published_at' => '2026-01-15 10:00:00']);

        $this->assertSame(['Draft'], $this->titles(['filter' => ['status' => 'draft']]));
        $this->assertSame(['Draft', 'New', 'Old'], $this->titles(['filter' => ['status' => ['draft', 'published']], 'sort' => 'title']));
        $this->assertSame(['Old'], $this->titles(['filter' => ['published_at' => ['to' => '2020-01-15']]]));
        $this->assertSame(['New'], $this->titles(['filter' => ['published_at' => ['from' => '2020-01-16']]]));
        $this->assertSame([], $this->titles(['filter' => ['published_at' => ['from' => '9999-12-31']]]));
    }

    public function test_a_filter_cannot_change_a_param_of_the_scope()
    {
        $this->article(['title' => 'Ours']);
        $this->article(['title' => 'Theirs', 'workspace' => 'urbania']);

        $this->assertSame(['Ours'], $this->titles(['filter' => ['workspace' => 'urbania']]));
    }

    public function test_a_filter_cannot_change_a_param_of_the_default_context()
    {
        $this->app->instance(QueryContextResolver::class, new class implements QueryContextResolver
        {
            public function resolve(EntitiesContract $repository): array
            {
                return ['workspace' => 'folklore'];
            }
        });
        $this->article(['title' => 'Ours']);
        $this->article(['title' => 'Theirs', 'workspace' => 'urbania']);

        $this->assertSame(['Ours'], $this->titles(['filter' => ['workspace' => 'urbania']], []));
    }

    public function test_a_filter_cannot_set_a_reserved_param()
    {
        $this->article(['title' => 'Montreal']);
        $this->article(['title' => 'Quebec']);

        $page = $this->adapter()->query(new ResourceQuery(filters: ['search' => 'mont', 'offset' => 1, 'identifier' => 'x'], scope: self::SCOPE));

        $this->assertSame(2, $page->total());
        $this->assertCount(2, $page->items());
    }

    public function test_a_list_is_ordered_by_the_entity_key_last()
    {
        $ids = [$this->article(['title' => 'Same'])->id(), $this->article(['title' => 'Same'])->id(), $this->article(['title' => 'Same'])->id()];
        sort($ids);

        $page = $this->adapter()->query(new ResourceQuery(sorts: [new Sort('title')], scope: self::SCOPE));

        $this->assertSame($ids, array_map(fn (Entity $entity) => $entity->id(), $page->items()));
    }

    public function test_a_list_is_searched_by_the_repository()
    {
        $this->article(['title' => 'Montreal']);
        $this->article(['title' => 'Quebec']);
        $this->article(['title' => 'Montreal', 'workspace' => 'urbania']);

        $this->assertSame(['Montreal'], $this->titles(['q' => 'mont']));
    }

    public function test_a_list_sorts_only_by_the_orderable_columns()
    {
        $this->article(['title' => 'B']);
        $this->article(['title' => 'A']);

        // `uid` isn't one of the repository's orderable columns: the sort is ignored
        $query = new ResourceQuery(sorts: [new Sort('uid', SortDirection::Descending)], scope: self::SCOPE);
        $page = $this->adapter()->query($query);

        $this->assertCount(2, $page->items());
    }

    public function test_a_list_has_the_ids_and_not_the_exclusions()
    {
        $first = $this->article(['title' => 'First']);
        $second = $this->article(['title' => 'Second']);
        $other = $this->article(['title' => 'Other', 'workspace' => 'urbania']);

        $query = (new ResourceQuery(scope: self::SCOPE))->withIds([$first->id(), $second->id(), $other->id()])->withExcept([$second->id()]);
        $ids = array_map(fn (Entity $entity) => $entity->id(), $this->adapter()->query($query)->items());

        $this->assertSame([$first->id()], $ids);
    }

    public function test_find_finds_an_entity_by_its_public_id_within_the_scope()
    {
        $article = $this->article();
        $other = $this->article(['workspace' => 'urbania']);

        $this->assertStringStartsWith('art_', $article->id());
        $this->assertSame($article->id(), $this->adapter()->find($article->id(), self::SCOPE)?->id());
        $this->assertSame($article->id(), $this->adapter()->id($article));
        $this->assertNull($this->adapter()->find($other->id(), self::SCOPE));
        $this->assertNull($this->adapter()->find((string) $article->getModel()->getKey(), self::SCOPE));
    }

    public function test_create_finds_the_entity_again_within_the_scope()
    {
        $adapter = $this->adapter();

        $created = $adapter->create(['title' => 'Created', 'slug' => ['fr' => 'cree'], 'workspace' => 'folklore'], self::SCOPE);

        $this->assertInstanceOf(ArticleEntity::class, $created);
        $this->assertSame('folklore', $created->workspace());
        $this->assertSame('cree', $created->slug('fr'));
        $found = $adapter->find($created->id(), self::SCOPE);
        $this->assertNotNull($found);
        $this->assertSame($adapter->version($found), $adapter->version($created));
    }

    public function test_create_does_not_write_the_params_of_the_scope()
    {
        try {
            $this->adapter()->create(['title' => 'Created'], self::SCOPE);
            $this->fail('An entity created outside its scope was kept.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('created an entity outside the scope of its resource', $exception->getMessage());
        }

        $this->assertSame(0, Article::query()->withTrashed()->count());
    }

    public function test_create_outside_the_scope_is_rolled_back()
    {
        try {
            $this->adapter()->create(['title' => 'Created', 'workspace' => 'urbania'], self::SCOPE);
            $this->fail('An entity created outside its scope was kept.');
        } catch (LogicException) {
        }

        $this->assertSame(0, Article::query()->withTrashed()->count());
    }

    public function test_update_changes_the_values_and_the_version()
    {
        $adapter = $this->adapter();
        $article = $adapter->find($this->article(['title' => 'Before'])->id(), self::SCOPE);
        $this->assertNotNull($article);

        $updated = $adapter->update($article, ['title' => 'After']);

        $this->assertInstanceOf(ArticleEntity::class, $updated);
        $this->assertSame('After', $updated->title());
        $this->assertSame($article->id(), $updated->id());
        $this->assertNotSame($adapter->version($article), $adapter->version($updated));
        $found = $adapter->find($article->id(), self::SCOPE);
        $this->assertNotNull($found);
        $this->assertSame($adapter->version($found), $adapter->version($updated));
    }

    public function test_update_fails_when_the_entity_is_gone()
    {
        $article = $this->article();
        Article::query()->whereKey($article->getModel()->getKey())->forceDelete();

        $this->expectException(ModelNotFoundException::class);

        $this->adapter()->update($article, ['title' => 'After']);
    }

    public function test_delete_fails_when_the_entity_is_gone()
    {
        $article = $this->article();
        Article::query()->whereKey($article->getModel()->getKey())->forceDelete();

        $this->expectException(ModelNotFoundException::class);

        $this->adapter()->delete($article);
    }

    public function test_delete_destroys_the_entity()
    {
        $adapter = $this->adapter();
        $article = $this->article();

        $adapter->delete($article);

        $this->assertNull($adapter->find($article->id(), self::SCOPE));
    }

    public function test_the_version_ignores_what_reading_the_entity_changes()
    {
        $adapter = $this->adapter();
        $article = $adapter->find($this->article(['published_at' => '2026-01-15 10:00:00'])->id(), self::SCOPE);
        $this->assertInstanceOf(ArticleEntity::class, $article);
        $version = $adapter->version($article);

        $article->getModel()->setAttribute('title', 'Not saved');

        $this->assertNotNull($version);
        $this->assertSame($version, $adapter->version($article));
    }

    public function test_an_item_that_is_not_an_entity_has_no_id_and_no_version()
    {
        $this->assertNull($this->adapter()->version(new stdClass));

        $this->expectException(InvalidArgumentException::class);

        $this->adapter()->id(new stdClass);
    }

    public function test_slug_exists_looks_up_the_column_of_the_locale_within_the_scope()
    {
        $adapter = $this->adapter();
        $article = $this->article(['slug_fr' => 'bonjour', 'slug_en' => 'hello']);
        $this->article(['slug_fr' => 'ailleurs', 'workspace' => 'urbania']);

        $this->assertTrue($adapter->slugExists('slug', 'bonjour', 'fr', null, self::SCOPE, []));
        $this->assertFalse($adapter->slugExists('slug', 'bonjour', 'en', null, self::SCOPE, []));
        $this->assertTrue($adapter->slugExists('slug', 'hello', 'en', null, self::SCOPE, []));
        $this->assertFalse($adapter->slugExists('slug', 'bonjour', 'fr', $article->id(), self::SCOPE, []));
        $this->assertFalse($adapter->slugExists('slug', 'ailleurs', 'fr', null, self::SCOPE, []));
        $this->assertTrue($adapter->slugExists('slug', 'ailleurs', 'fr', null, ['workspace' => 'urbania'], []));
    }

    public function test_slug_exists_fails_for_a_repository_that_cannot_check_slugs()
    {
        $adapter = new EntitiesRepository(new Notes);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('can\'t tell whether a slug is taken');

        $adapter->slugExists('slug', 'bonjour', null, null, [], []);
    }
}
