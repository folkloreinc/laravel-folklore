<?php

namespace Folklore\Upgrade;

use Composer\Autoload\ClassLoader;
use ReflectionClass;
use Symfony\Component\Process\Process;

/**
 * Loads a class of the site in a child PHP process first. A 1.1 class can be a fatal
 * error in 2.0 that no `catch` stops: an incompatible signature, a missing abstract
 * method, a parent that changed. The child dies instead of the report, and the class is
 * loaded here only once it loaded there.
 */
class IsolatedLoader
{
    public const MISSING = 'no such class';

    /**
     * Why each class that was tried didn't load, or null when it did.
     *
     * @var array<string, string|null>
     */
    protected array $results = [];

    /**
     * @param  string|null  $autoload  The Composer autoloader of the child; null: the one this
     *                                 process uses.
     */
    public function __construct(protected ?string $autoload = null) {}

    /**
     * Whether the class loads, in the child process and then here.
     */
    public function loads(string $class): bool
    {
        return $this->error($class) === null;
    }

    /**
     * Why the class doesn't load, or null when it does: `no such class` when no file
     * declares it, or the error that loading it raised.
     */
    public function error(string $class): ?string
    {
        if (! array_key_exists($class, $this->results)) {
            $this->results[$class] = $this->tryLoading($class);
        }

        return $this->results[$class];
    }

    /**
     * The errors raised by the classes that were tried, without those that don't exist.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        return array_filter($this->results, fn (?string $error) => $error !== null && $error !== self::MISSING);
    }

    protected function tryLoading(string $class): ?string
    {
        if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
            return null;
        }

        $process = new Process([
            PHP_BINARY,
            '-d', 'display_errors=stderr',
            '-r', 'require $argv[1]; exit(class_exists($argv[2]) || interface_exists($argv[2]) || trait_exists($argv[2]) ? 0 : 3);',
            '--',
            $this->autoload(),
            $class,
        ]);
        $process->setTimeout(60);
        $process->run();

        if ($process->getExitCode() === 3) {
            return self::MISSING;
        }
        if (! $process->isSuccessful()) {
            $output = trim($process->getErrorOutput()."\n".$process->getOutput());
            $line = strtok($output, "\n");

            return $line === false ? 'the PHP process failed' : preg_replace('/^PHP\s+/', '', $line);
        }

        try {
            return class_exists($class) ? null : self::MISSING;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    protected function autoload(): string
    {
        if (is_null($this->autoload)) {
            $loader = (string) (new ReflectionClass(ClassLoader::class))->getFileName();
            $this->autoload = dirname($loader, 2).'/autoload.php';
        }

        return $this->autoload;
    }
}
