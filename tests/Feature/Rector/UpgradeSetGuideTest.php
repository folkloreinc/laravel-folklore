<?php

namespace Folklore\Tests\Feature\Rector;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * The Rector set of the upgrade from 1.1 with the config of docs/upgrade.md, without
 * `withImportNames()`: the new names are fully qualified, and the old `use` statements stay.
 */
class UpgradeSetGuideTest extends AbstractRectorTestCase
{
    #[DataProvider('provideFiles')]
    public function test_it_upgrades_the_files_of_a_site_as_the_guide_runs_it(string $file): void
    {
        $this->doTestFile($file);
    }

    public static function provideFiles(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__.'/GuideFixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__.'/config/upgrade-2.0-guide.php';
    }
}
