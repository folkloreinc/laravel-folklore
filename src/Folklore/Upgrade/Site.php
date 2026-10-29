<?php

namespace Folklore\Upgrade;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * The site that `folklore:upgrade --check` looks at: its code, read as text so that a
 * class that no longer loads in 2.0 is still found, its config, and its database through
 * its models, which are loaded in a child process first (IsolatedLoader).
 */
class Site
{
    /**
     * The paths read by default, from the site's base path.
     *
     * @var list<string>
     */
    public const PATHS = ['app', 'config', 'routes', 'resources/views', 'bootstrap/app.php', 'bootstrap/providers.php'];

    /**
     * The deployment and build files read for the commands of the modules, from the site's
     * base path: a command that 2.0 registers only with its module fails the build.
     *
     * @var list<string>
     */
    public const DEPLOYMENT_FILES = [
        'Envoy.blade.php',
        'composer.json',
        'package.json',
        '.github/workflows/*.yml',
        '.github/workflows/*.yaml',
        '.gitlab-ci.yml',
        'deploy*.sh',
        'Dockerfile*',
    ];

    /**
     * @var list<SourceFile>|null
     */
    protected ?array $files = null;

    /**
     * @var list<SourceFile>|null
     */
    protected ?array $deploymentFiles = null;

    public readonly IsolatedLoader $loader;

    /**
     * @param  list<string>  $paths  Directories and files to read, from the base path or absolute.
     * @param  list<class-string<Model>>  $models  Models whose JSON data is read, besides those
     *                                             declared in the paths.
     * @param  int  $limit  How many rows of each model to read; 0 reads them all.
     */
    public function __construct(
        public readonly string $basePath,
        public readonly Repository $config,
        public readonly ConnectionResolverInterface $db,
        public readonly array $paths = self::PATHS,
        public readonly array $models = [],
        public readonly int $limit = 1000,
        ?IsolatedLoader $loader = null,
    ) {
        $this->loader = $loader ?? new IsolatedLoader;
    }

    /**
     * @return list<SourceFile>
     */
    public function files(): array
    {
        if (is_null($this->files)) {
            $this->files = [];
            foreach ($this->paths as $path) {
                $absolute = $this->absolute($path);
                if (is_file($absolute)) {
                    $this->files[] = SourceFile::read($absolute, $this->relative($absolute));
                } elseif (is_dir($absolute)) {
                    $found = [];
                    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute, RecursiveDirectoryIterator::SKIP_DOTS));
                    foreach ($iterator as $file) {
                        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                            $found[] = $file->getPathname();
                        }
                    }
                    sort($found);
                    foreach ($found as $file) {
                        $this->files[] = SourceFile::read($file, $this->relative($file));
                    }
                }
            }
        }

        return $this->files;
    }

    /**
     * The deployment and build files of the site (`DEPLOYMENT_FILES`), whichever paths
     * are read: `Envoy.blade.php`, the scripts of `composer.json` and `package.json`, the
     * CI workflows, `deploy*.sh` and the `Dockerfile`.
     *
     * @return list<SourceFile>
     */
    public function deploymentFiles(): array
    {
        if (is_null($this->deploymentFiles)) {
            $found = [];
            foreach (self::DEPLOYMENT_FILES as $pattern) {
                foreach (glob($this->absolute($pattern)) ?: [] as $file) {
                    if (is_file($file)) {
                        $found[] = $file;
                    }
                }
            }
            $found = array_values(array_unique($found));
            $this->deploymentFiles = array_map(fn (string $file) => SourceFile::read($file, $this->relative($file)), $found);
        }

        return $this->deploymentFiles;
    }

    /**
     * The lines that match a pattern, in every file or in those whose path starts with
     * one of `$in`.
     *
     * @param  list<string>  $in
     * @return list<Occurrence>
     */
    public function matches(string $pattern, array $in = []): array
    {
        $files = count($in) > 0
            ? array_filter($this->files(), fn (SourceFile $file) => $this->isIn($file->path, $in))
            : $this->files();

        return $this->matchLines($files, $pattern);
    }

    /**
     * The lines of the deployment and build files that match a pattern.
     *
     * @return list<Occurrence>
     */
    public function deploymentMatches(string $pattern): array
    {
        return $this->matchLines($this->deploymentFiles(), $pattern);
    }

    /**
     * The files whose code, over several lines, matches a pattern.
     *
     * @return list<SourceFile>
     */
    public function filesMatching(string $pattern): array
    {
        return array_values(array_filter($this->files(), fn (SourceFile $file) => preg_match($pattern, $file->code()) === 1));
    }

    /**
     * The lines that name a class, or a namespace when the name ends with a backslash: in
     * `use` statements, fully qualified names and strings (`'Folklore\\Models\\Media'`).
     *
     * @return list<Occurrence>
     */
    public function references(string $name): array
    {
        return $this->matches(self::referencePattern($name));
    }

    /**
     * Whether a file names a class, or a namespace when the name ends with a backslash.
     */
    public function fileReferences(SourceFile $file, string $name): bool
    {
        return preg_match(self::referencePattern($name), $file->code()) === 1;
    }

    public static function referencePattern(string $name): string
    {
        $namespace = str_ends_with($name, '\\');
        $segments = array_map(fn (string $segment) => preg_quote($segment, '/'), explode('\\', trim($name, '\\')));

        return '/(?<![\w\\\\$])(?:\\\\{1,2})?'.implode('\\\\{1,2}', $segments).($namespace ? '\\\\{1,2}\w' : '(?![\w\\\\])').'/';
    }

    /**
     * A config value of the site.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    /**
     * Whether the site turned a module on in `config/site.php`.
     */
    public function moduleEnabled(string $module): bool
    {
        return (bool) $this->config('site.modules.'.$module, false);
    }

    /**
     * The packages the site requires itself, in its `composer.json`.
     *
     * @return list<string>
     */
    public function requiredPackages(): array
    {
        $contents = @file_get_contents($this->absolute('composer.json'));
        $composer = is_string($contents) ? json_decode($contents, true) : null;
        $packages = [];
        foreach (['require', 'require-dev'] as $section) {
            $require = is_array($composer) ? ($composer[$section] ?? null) : null;
            if (is_array($require)) {
                $packages = [...$packages, ...array_map('strval', array_keys($require))];
            }
        }

        return $packages;
    }

    /**
     * Whether the site has a `composer.json` to read its packages from.
     */
    public function hasComposerFile(): bool
    {
        return is_file($this->absolute('composer.json'));
    }

    /**
     * The models whose JSON data the checks read: those given to the command, and the
     * classes of the paths whose text looks like a model and that load.
     *
     * @return list<class-string<Model>>
     */
    public function models(): array
    {
        $models = $this->models;
        foreach ($this->files() as $file) {
            $class = $file->looksLikeModel() ? $file->declaredClass() : null;
            if (! is_null($class) && $this->loads($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $models[] = $class;
            }
        }

        return array_values(array_unique($models));
    }

    /**
     * Whether a class loads, tried in a child process first: a class of the site may
     * extend one that 2.0 removed or changed, which can be a fatal error.
     */
    public function loads(string $class): bool
    {
        return $this->loader->loads($class);
    }

    public function absolute(string $path): string
    {
        return str_starts_with($path, '/') ? $path : rtrim($this->basePath, '/').'/'.$path;
    }

    public function relative(string $path): string
    {
        $base = rtrim($this->basePath, '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    /**
     * @param  iterable<SourceFile>  $files
     * @return list<Occurrence>
     */
    protected function matchLines(iterable $files, string $pattern): array
    {
        $occurrences = [];
        foreach ($files as $file) {
            foreach ($file->lines as $number => $line) {
                if ($line !== '' && preg_match($pattern, $line, $groups) === 1) {
                    $occurrences[] = new Occurrence($file, $number, array_values($groups));
                }
            }
        }

        return $occurrences;
    }

    /**
     * @param  list<string>  $prefixes
     */
    protected function isIn(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
