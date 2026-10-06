<?php

namespace Folklore\Tests\Feature\Console;

use Folklore\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class AssetsViewCommandTest extends TestCase
{
    public function test_it_is_registered_outside_the_local_environment()
    {
        $this->assertSame('testing', $this->app->environment());
        $this->assertArrayHasKey('assets:view', Artisan::all());
    }

    public function test_the_generators_stay_local_only()
    {
        $commands = Artisan::all();

        foreach (
            [
                'make:entity',
                'make:entity-contract',
                'make:entity-model',
                'make:repository',
                'make:repository-contract',
            ] as $name
        ) {
            $this->assertArrayNotHasKey($name, $commands);
        }
    }
}
