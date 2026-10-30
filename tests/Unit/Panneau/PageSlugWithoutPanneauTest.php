<?php

namespace Folklore\Tests\Unit\Panneau;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * `PageSlug` while a site upgrades from 1.1 with laravel-panneau 1.x, without Panneau 5's
 * classes: the 1.x admin calls `PageSlug::setRoutesResolver()` at boot (#138).
 */
#[Group('core')]
class PageSlugWithoutPanneauTest extends TestCase
{
    public function test_the_1x_routes_resolver_does_nothing_without_panneau_5()
    {
        // The package's autoloader, without the classes of Panneau 5
        $script = <<<'PHP'
        $loader = require $argv[1];
        $loader->unregister();
        spl_autoload_register(fn (string $class) => str_starts_with($class, 'Panneau\\') ? null : $loader->loadClass($class), true, true);

        Folklore\Panneau\Fields\PageSlug::setRoutesResolver(fn ($locale) => []);

        echo (new ReflectionClass(Folklore\Panneau\Fields\PageSlug::class))->getName();
        PHP;

        $process = new Process([PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'error_reporting=-1', '-r', $script, '--', __DIR__.'/../../../vendor/autoload.php']);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame('Folklore\\Panneau\\Fields\\LegacyPageSlug', $process->getOutput());
        $this->assertStringContainsString('PageSlug::setRoutesResolver() does nothing', $process->getErrorOutput());
    }
}
