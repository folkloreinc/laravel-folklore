<?php

namespace Folklore\Tests\Feature\Rector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * What the Rector set writes in a site's repository that implements Panneau 1.x's
 * `Repository` on its own still loads while laravel-panneau 1.x is installed.
 */
class RepositoryOutputLoadsTest extends TestCase
{
    public function test_the_upgraded_repository_loads_with_panneau_1x_contracts()
    {
        [, $output] = explode("-----\n", (string) file_get_contents(__DIR__.'/Fixture/ExternalRepository.php.inc'));
        $file = (string) tempnam(sys_get_temp_dir(), 'folklore-rector-').'.php';
        file_put_contents($file, $output);

        $process = new Process([
            PHP_BINARY,
            '-d', 'display_errors=stderr',
            '-r', 'require $argv[1]; require $argv[2]; require $argv[3]; echo class_exists(App\Repositories\Events::class) ? "loaded" : "missing";',
            '--',
            __DIR__.'/../../../vendor/autoload.php',
            __DIR__.'/Stubs/panneau-1x-contracts.php',
            $file,
        ]);
        $process->run();
        unlink($file);

        $this->assertSame('loaded', $process->getOutput(), $process->getErrorOutput());
    }
}
