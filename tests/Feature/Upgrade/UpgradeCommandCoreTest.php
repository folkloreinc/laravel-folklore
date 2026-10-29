<?php

namespace Folklore\Tests\Feature\Upgrade;

use Folklore\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * `folklore:upgrade --check` runs in a site that has none of the modules' packages yet:
 * it reads the site's code as text. CI runs it without them (the `core` group).
 */
#[Group('core')]
class UpgradeCommandCoreTest extends TestCase
{
    protected function modules(): array
    {
        return [];
    }

    public function test_it_checks_a_site_without_the_packages_of_the_modules()
    {
        $this->artisan('folklore:upgrade', ['--check' => true, '--path' => [__DIR__.'/../../fixture/upgrade-site/app', __DIR__.'/../../fixture/upgrade-site/config']])
            ->expectsOutputToContain('The site uses the `medias` module, which is off')
            ->expectsOutputToContain('`Folklore\\Mediatheque\\Support\\Traits\\HasMedias` is gone')
            ->assertExitCode(1);
    }
}
