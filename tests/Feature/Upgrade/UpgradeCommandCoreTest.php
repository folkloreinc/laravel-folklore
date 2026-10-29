<?php

namespace Folklore\Tests\Feature\Upgrade;

use Folklore\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * `folklore:upgrade` runs in a site that has none of the modules' packages yet: it reads
 * the site's code as text. CI runs it without them (the `core` group).
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

    public function test_it_leaves_off_a_module_whose_packages_are_missing()
    {
        if (trait_exists('Folklore\\Mediatheque\\Concerns\\HasMedias')) {
            $this->markTestSkipped('The mediatheque is installed: CI runs this test without it.');
        }
        $this->app->setBasePath(__DIR__.'/../../fixture/upgrade-site');

        $this->artisan('folklore:upgrade', ['--dry-run' => true])
            ->expectsOutputToContain('The site uses the `medias` module, which needs folklore/laravel-mediatheque ^2.0')
            ->expectsOutputToContain("Would set medias.resource → 'legacy'")
            ->doesntExpectOutputToContain('Would set modules.medias')
            ->assertExitCode(0);
    }
}
