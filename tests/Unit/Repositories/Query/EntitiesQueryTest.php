<?php

namespace Folklore\Tests\Unit\Repositories\Query;

use Folklore\Repositories\Query\EntitiesQuery;
use Folklore\Repositories\Query\Sort;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('core')]
class EntitiesQueryTest extends TestCase
{
    public function test_a_query_has_defaults()
    {
        $query = new EntitiesQuery;

        $this->assertSame([], $query->filters);
        $this->assertNull($query->search);
        $this->assertSame([], $query->sorts);
        $this->assertNull($query->ids);
        $this->assertSame([], $query->except);
        $this->assertSame(1, $query->page);
        $this->assertSame(20, $query->perPage);
    }

    public function test_withers_return_copies()
    {
        $query = new EntitiesQuery(filters: ['type' => 'article']);

        $changed = $query
            ->withFilter('published', true)
            ->withSearch('  prize  ')
            ->withSorts([Sort::descending('published_at')])
            ->withIds(['3', '1', '3'])
            ->withExcept(['2', '2'])
            ->withPage(2, 50);

        $this->assertSame(['type' => 'article'], $query->filters);
        $this->assertNull($query->search);
        $this->assertSame(['type' => 'article', 'published' => true], $changed->filters);
        $this->assertSame('prize', $changed->search);
        $this->assertSame('-published_at', $changed->sorts[0]->toString());
        $this->assertSame(['3', '1'], $changed->ids);
        $this->assertSame(['2'], $changed->except);
        $this->assertSame(2, $changed->page);
        $this->assertSame(50, $changed->perPage);
        $this->assertSame(50, $changed->withPage(3)->perPage);
    }

    public function test_an_empty_search_is_no_search()
    {
        $this->assertNull((new EntitiesQuery)->withSearch('   ')->search);
        $this->assertNull((new EntitiesQuery(search: 'a'))->withSearch(null)->search);
    }

    public function test_ids_can_be_unrestricted_again()
    {
        $this->assertNull((new EntitiesQuery(ids: ['1']))->withIds(null)->ids);
        $this->assertSame([], (new EntitiesQuery)->withIds([])->ids);
    }

    public function test_filters_are_read_by_name()
    {
        $query = (new EntitiesQuery)->withFilters(['type' => ['article', 'video']]);

        $this->assertSame(['article', 'video'], $query->filter('type'));
        $this->assertNull($query->filter('published'));
    }

    public function test_the_page_and_its_size_are_positive()
    {
        foreach ([fn () => new EntitiesQuery(page: 0), fn () => new EntitiesQuery(perPage: 0), fn () => (new EntitiesQuery)->withPage(-1)] as $make) {
            try {
                $make();
                $this->fail('A page below 1 was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('must be 1 or more', $exception->getMessage());
            }
        }
    }
}
