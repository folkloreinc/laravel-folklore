<?php

namespace Folklore\Tests\Unit\Support;

use Folklore\Support\OffsetPaginator;
use PHPUnit\Framework\TestCase;

class OffsetPaginatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        OffsetPaginator::currentPageResolver(fn () => 0);
    }

    public function test_offset_and_next_offset()
    {
        $paginator = new OffsetPaginator(collect([1, 2, 3]), 10, 3);

        $this->assertSame(3, $paginator->currentOffset());
        $this->assertSame(6, $paginator->nextOffset());
        $this->assertSame(3, $paginator->perPage());
        $this->assertSame(10, $paginator->total());
        $this->assertTrue($paginator->hasMore());
        $this->assertSame('/?offset=6', $paginator->nextOffsetUrl());
    }

    public function test_last_slice()
    {
        $paginator = new OffsetPaginator(collect([9, 10]), 10, 8);

        $this->assertFalse($paginator->hasMore());
        $this->assertNull($paginator->nextOffsetUrl());
    }

    public function test_falls_back_to_the_resolved_offset()
    {
        OffsetPaginator::currentPageResolver(fn () => 4);

        $paginator = new OffsetPaginator(collect([5, 6]), 10);

        $this->assertSame(4, $paginator->currentOffset());
    }

    public function test_invalid_offset_is_clamped_to_the_total()
    {
        $paginator = new OffsetPaginator(collect([]), 10, 20);

        $this->assertSame(10, $paginator->currentOffset());
        $this->assertFalse($paginator->hasMore());
    }

    public function test_serialization()
    {
        $paginator = new OffsetPaginator(collect([1, 2, 3]), 10, 3);
        $array = $paginator->toArray();

        $this->assertSame(3, $array['current_offset']);
        $this->assertSame([1, 2, 3], $array['data']);
        $this->assertSame('/?offset=6', $array['next_offset_url']);
        $this->assertSame(3, $array['per_page']);
        $this->assertSame($array, $paginator->jsonSerialize());
        $this->assertSame(json_encode($array), $paginator->toJson());
    }
}
