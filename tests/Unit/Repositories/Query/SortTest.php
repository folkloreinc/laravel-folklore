<?php

namespace Folklore\Tests\Unit\Repositories\Query;

use Folklore\Repositories\Query\Sort;
use Folklore\Repositories\Query\SortDirection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('core')]
class SortTest extends TestCase
{
    public function test_a_sort_is_parsed_with_its_direction()
    {
        $this->assertEquals(new Sort('title'), Sort::parse('title'));
        $this->assertEquals(new Sort('published_at', SortDirection::Descending), Sort::parse('-published_at'));
        $this->assertTrue(Sort::descending('title')->isDescending());
        $this->assertFalse(Sort::ascending('title')->isDescending());
    }

    public function test_a_list_of_sorts_is_parsed_in_order()
    {
        $sorts = Sort::parseList(' -published_at, title ,, - ');

        $this->assertSame(['-published_at', 'title'], array_map(fn (Sort $sort): string => $sort->toString(), $sorts));
        $this->assertSame([], Sort::parseList(''));
    }
}
