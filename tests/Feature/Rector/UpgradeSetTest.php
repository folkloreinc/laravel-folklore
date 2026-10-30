<?php

namespace Folklore\Tests\Feature\Rector;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\NodeTypeResolver\Reflection\BetterReflection\SourceLocatorProvider\DynamicSourceLocatorProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * The Rector set of the upgrade from 1.1 (`FolkloreSetList::UPGRADE_2_0`), on the files of
 * a 1.1 site: tests/fixture/upgrade-site, and what the active 1.1 sites do. Each fixture
 * is the file before and after.
 */
class UpgradeSetTest extends AbstractRectorTestCase
{
    #[DataProvider('provideFiles')]
    public function test_it_upgrades_the_files_of_a_site(string $file): void
    {
        // A site that upgrades has laravel-panneau 1.x's contracts
        $this->make(DynamicSourceLocatorProvider::class)->addDirectories([__DIR__.'/Stubs']);

        $this->doTestFile($file);
    }

    public static function provideFiles(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__.'/Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__.'/config/upgrade-2.0.php';
    }
}
