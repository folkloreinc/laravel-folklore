<?php

namespace Folklore\Tests\Feature\Modules;

use Folklore\Eloquent\JsonData;
use Folklore\ServiceProvider;
use Folklore\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;

/**
 * The core boots with every module off. CI runs the `core` group without the
 * packages the modules need (docs/decisions/0002).
 */
#[Group('core')]
class CoreOnlyTest extends TestCase
{
    protected function modules(): array
    {
        return [];
    }

    public function test_the_package_boots_with_every_module_off()
    {
        $this->assertTrue($this->app->providerIsLoaded(ServiceProvider::class));
        $this->assertSame(false, $this->app['config']->get('site.modules.medias'));
        $this->assertContains('daemon:restart', array_keys(Artisan::all()));
        $this->assertSame([], JsonData::keys());
    }
}
