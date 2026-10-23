<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Tests\Feature\Repositories\Fixtures\Notes;
use Folklore\Tests\TestCase;

/**
 * The notes table for the base repository's tests.
 */
abstract class NotesTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--path' => __DIR__.'/Fixtures/migrations',
            '--realpath' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Notes::$byUid = false;

        parent::tearDown();
    }
}
