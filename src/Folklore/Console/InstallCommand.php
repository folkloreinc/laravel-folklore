<?php

namespace Folklore\Console;

use Folklore\ServiceProvider as FolkloreServiceProvider;
use Folklore\Upgrade\ConfigFile;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PhpToken;
use RuntimeException;
use Throwable;

/**
 * Writes in a site the classes of the standard resources of the panneau module, which
 * extend the package's, and wires them (docs/decisions/0025):
 *
 * - `App\Panneau\Resources\{Pages,Users,Medias}`;
 * - the site's contracts, entities, models and repositories of pages and users, bound to the
 *   package's contracts and their own in `site.repositories`;
 * - the standard block types in `App\Entities\Blocks`, in `site.pages.blocks.types`;
 * - the modules they need, in `config/site.php`;
 * - `Panneau::resources([...])` in `App\Providers\PanneauServiceProvider`, created if missing.
 *
 * It skips a file that exists, and reports a class of the site that doesn't extend the
 * package's yet, without rewriting it: it can run again to add a resource later. A value of
 * `config/site.php` that the site set is kept.
 */
class InstallCommand extends Command
{
    /**
     * The standard resources, which `--only` chooses among.
     */
    public const RESOURCES = ['pages', 'users', 'medias'];

    /**
     * The standard block types, by id: the class of each, in `Folklore\Entities\Blocks`.
     */
    public const BLOCKS = [
        'text' => 'TextBlock',
        'heading' => 'HeadingBlock',
        'image' => 'ImageBlock',
        'gallery' => 'GalleryBlock',
        'quote' => 'QuoteBlock',
        'button' => 'ButtonBlock',
        'embed' => 'EmbedBlock',
    ];

    protected $signature = 'folklore:install
        {--only= : The resources to install, separated by commas: pages, users, medias (all by default)}
        {--dry-run : Show what would be written, without writing anything}
        {--force : Overwrite the classes that exist, and the values the site set in config/site.php}';

    protected $description = 'Write the standard Panneau resources of laravel-folklore in the site: their classes, block types and wiring';

    protected bool $dryRun = false;

    protected bool $force = false;

    /**
     * The classes of the site that don't extend the package's.
     *
     * @var list<string>
     */
    protected array $notExtending = [];

    /**
     * What the site sets itself.
     *
     * @var list<string>
     */
    protected array $todo = [];

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $resources = $this->resources();
        if ($resources === null) {
            return self::FAILURE;
        }
        $this->dryRun = (bool) $this->option('dry-run');
        $this->force = (bool) $this->option('force');
        if ($this->dryRun) {
            $this->components->info('Dry run: nothing is written.');
        }

        foreach ($resources as $resource) {
            $this->line('<options=bold>'.ucfirst($resource).'</>');
            foreach ($this->classes($resource) as $class) {
                $this->writeClass($class);
            }
        }

        $this->line('<options=bold>config/site.php</>');
        $this->writeConfig($resources);

        $this->line('<options=bold>Panneau</>');
        $this->writePanneauProvider($resources);

        $this->newLine();
        foreach ($this->notExtending as $message) {
            $this->components->warn($message);
        }
        foreach ($this->todo as $message) {
            $this->components->warn($message);
        }
        if ($this->laravel->configurationIsCached()) {
            $this->components->warn('The configuration is cached: run `php artisan config:cache` again for config/site.php to apply.');
        }
        $this->components->info($this->dryRun ? 'Nothing was written.' : 'The standard resources are installed.');

        return self::SUCCESS;
    }

    /**
     * The resources of `--only`, or all of them; null for one that doesn't exist.
     *
     * @return list<string>|null
     */
    protected function resources(): ?array
    {
        $only = $this->option('only');
        if (! is_string($only) || trim($only) === '') {
            return self::RESOURCES;
        }
        $resources = array_values(array_unique(array_filter(array_map('trim', explode(',', strtolower($only))))));
        $unknown = array_diff($resources, self::RESOURCES);
        if (count($unknown) > 0) {
            $this->components->error(sprintf('Unknown resource: %s. Choose among %s.', implode(', ', $unknown), implode(', ', self::RESOURCES)));

            return null;
        }

        return array_values(array_intersect(self::RESOURCES, $resources));
    }

    /**
     * The classes of a resource: their stub, the class of the package they extend, and the
     * values of their stub.
     *
     * @return list<array{class: string, stub: string, base: string, replace?: array<string, string>}>
     */
    protected function classes(string $resource): array
    {
        $app = $this->rootNamespace();

        return match ($resource) {
            'pages' => [
                ['class' => $app.'Contracts\\Entities\\Page', 'stub' => 'interface', 'base' => 'Folklore\\Contracts\\Entities\\Page'],
                ['class' => $app.'Entities\\Page', 'stub' => 'class', 'base' => 'Folklore\\Entities\\Page', 'replace' => ['implements' => ' implements \\'.$app.'Contracts\\Entities\\Page']],
                ['class' => $app.'Models\\Page', 'stub' => 'model', 'base' => 'Folklore\\Models\\Page', 'replace' => [
                    'contract' => $app.'Contracts\\Entities\\Page',
                    'entity' => $app.'Entities\\Page',
                    'typed' => '$this->toTypedEntity() ?? ',
                ]],
                ['class' => $app.'Contracts\\Repositories\\Pages', 'stub' => 'interface', 'base' => 'Folklore\\Contracts\\Repositories\\Pages'],
                ['class' => $app.'Repositories\\Pages', 'stub' => 'repository', 'base' => 'Folklore\\Repositories\\Pages', 'replace' => [
                    'contract' => $app.'Contracts\\Repositories\\Pages',
                    'model' => $app.'Models\\Page',
                    'modelBase' => 'Folklore\\Models\\Page',
                ]],
                ['class' => $app.'Panneau\\Resources\\Pages', 'stub' => 'class', 'base' => 'Folklore\\Panneau\\Resources\\Pages'],
                ...array_map(fn (string $block): array => [
                    'class' => $app.'Entities\\Blocks\\'.$block,
                    'stub' => 'class',
                    'base' => 'Folklore\\Entities\\Blocks\\'.$block,
                ], array_values(self::BLOCKS)),
            ],
            'users' => [
                ['class' => $app.'Contracts\\Entities\\User', 'stub' => 'interface', 'base' => 'Folklore\\Contracts\\Entities\\User'],
                ['class' => $app.'Entities\\User', 'stub' => 'class', 'base' => 'Folklore\\Entities\\User', 'replace' => ['implements' => ' implements \\'.$app.'Contracts\\Entities\\User']],
                ['class' => $app.'Models\\User', 'stub' => 'model', 'base' => 'Folklore\\Models\\User', 'replace' => [
                    'contract' => $app.'Contracts\\Entities\\User',
                    'entity' => $app.'Entities\\User',
                    'typed' => '',
                ]],
                ['class' => $app.'Contracts\\Repositories\\Users', 'stub' => 'interface', 'base' => 'Folklore\\Contracts\\Repositories\\Users'],
                ['class' => $app.'Repositories\\Users', 'stub' => 'repository', 'base' => 'Folklore\\Repositories\\Users', 'replace' => [
                    'contract' => $app.'Contracts\\Repositories\\Users',
                    'model' => $app.'Models\\User',
                    'modelBase' => 'Illuminate\\Database\\Eloquent\\Model',
                ]],
                ['class' => $app.'Panneau\\Resources\\Users', 'stub' => 'class', 'base' => 'Folklore\\Panneau\\Resources\\Users'],
            ],
            'medias' => [
                ['class' => $app.'Panneau\\Resources\\Medias', 'stub' => 'class', 'base' => 'Folklore\\Panneau\\Resources\\Medias'],
            ],
            default => [],
        };
    }

    /**
     * Writes a class, unless its file exists: then reports it when it doesn't extend the
     * package's class.
     *
     * @param  array{class: string, stub: string, base: string, replace?: array<string, string>}  $class
     */
    protected function writeClass(array $class): void
    {
        $path = $this->classPath($class['class']);
        $relative = $this->relative($path);
        $exists = $this->files->exists($path);
        if ($exists && ! $this->force) {
            if ($this->extendsBase($path, $class['class'], $class['base']) === false) {
                $this->notExtending[] = sprintf('%s doesn\'t extend %s yet: make it extend it, and remove what the package does (%s).', $class['class'], $class['base'], $relative);
                $this->components->twoColumnDetail($relative, '<fg=yellow>exists, doesn\'t extend '.$class['base'].'</>');
            } else {
                $this->components->twoColumnDetail($relative, '<fg=gray>exists</>');
            }

            return;
        }

        $namespace = substr($class['class'], 0, (int) strrpos($class['class'], '\\'));
        $code = $this->stub($class['stub'], [
            'namespace' => $namespace,
            'class' => class_basename($class['class']),
            'base' => $class['base'],
            'implements' => '',
            ...($class['replace'] ?? []),
        ]);
        if (! $this->dryRun) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $code);
        }
        $this->components->twoColumnDetail($relative, $this->status($exists ? 'overwritten' : 'created'));
    }

    /**
     * Writes the values of `config/site.php` that the resources need: their modules, the
     * pages' model and block types, and the repositories. A value that the site set is
     * kept, unless `--force`.
     *
     * @param  list<string>  $resources
     */
    protected function writeConfig(array $resources): void
    {
        $path = $this->laravel->configPath('site.php');
        try {
            $file = new ConfigFile((string) $this->files->get($this->files->exists($path) ? $path : __DIR__.'/../../../config/site.php'));
        } catch (Throwable $e) {
            $this->todo[] = 'config/site.php can\'t be read: '.$e->getMessage();

            return;
        }
        $app = $this->rootNamespace();
        $changed = false;

        $modules = ['panneau'];
        foreach ($resources as $resource) {
            $modules[] = match ($resource) {
                'users' => 'auth',
                default => $resource,
            };
        }
        foreach (array_unique($modules) as $module) {
            if (config('site.modules.'.$module) === true) {
                continue;
            }
            $missing = $this->missingPackages($module);
            if (count($missing) > 0) {
                $this->todo[] = sprintf('The %s module needs %s: require it, then set `site.modules.%s` to `true`.', $module, implode(' and ', $missing), $module);

                continue;
            }
            $changed = $this->set($file, 'modules.'.$module, true) || $changed;
        }

        $classes = [];
        if (in_array('pages', $resources, true)) {
            $classes['pages.model'] = [$app.'Models\\Page', config('site.pages.model') === null || config('site.pages.model') === 'Folklore\\Models\\Page'];
            foreach (self::BLOCKS as $id => $block) {
                $classes['pages.blocks.types.'.$id] = [$app.'Entities\\Blocks\\'.$block, config('site.pages.blocks.types.'.$id) === null];
            }
        }
        $repositories = config('site.repositories');
        $repositories = is_array($repositories) ? $repositories : [];
        foreach (['pages' => 'Pages', 'users' => 'Users'] as $resource => $name) {
            if (! in_array($resource, $resources, true)) {
                continue;
            }
            foreach ([$app.'Contracts\\Repositories\\'.$name, 'Folklore\\Contracts\\Repositories\\'.$name] as $contract) {
                $classes['repositories.'.$contract] = [$app.'Repositories\\'.$name, ! isset($repositories[$contract])];
            }
        }
        foreach ($classes as $key => [$class, $unset]) {
            if (! $unset && ! $this->force) {
                $this->components->twoColumnDetail('site.'.$key, '<fg=gray>set by the site</>');

                continue;
            }
            $changed = $this->set($file, $key, $class) || $changed;
        }

        if ($changed && ! $this->dryRun) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $file->code());
        }
    }

    /**
     * Sets a key of the config file, to a value or a class name, or notes it for the site.
     */
    protected function set(ConfigFile $file, string $key, bool|string $value): bool
    {
        try {
            is_string($value) ? $file->setClass($key, $value) : $file->set($key, $value);
        } catch (RuntimeException $e) {
            $this->todo[] = sprintf('`site.%s` can\'t be written in config/site.php (%s): set it to `%s` yourself.', $key, $e->getMessage(), is_string($value) ? '\\'.$value.'::class' : 'true');

            return false;
        }
        $this->components->twoColumnDetail('site.'.$key, $this->status('set').' <fg=gray>'.(is_string($value) ? $value : 'true').'</>');

        return true;
    }

    /**
     * Registers the resources in `App\Providers\PanneauServiceProvider`, created if missing,
     * with the policies of the standard resources.
     *
     * @param  list<string>  $resources
     */
    protected function writePanneauProvider(array $resources): void
    {
        $app = $this->rootNamespace();
        $provider = $app.'Providers\\PanneauServiceProvider';
        $path = $this->classPath($provider);
        $relative = $this->relative($path);
        $classes = array_map(fn (string $resource): string => $app.'Panneau\\Resources\\'.ucfirst($resource), $resources);
        $policies = array_map(fn (string $resource): string => $this->policy($resource), $resources);

        if (! $this->files->exists($path)) {
            $code = $this->stub('panneau-provider', [
                'namespace' => $app.'Providers',
                'resources' => implode('', array_map(fn (string $class): string => '            \\'.$class."::class,\n", $classes)),
                'policies' => implode('', array_map(fn (string $policy): string => '            '.$policy."\n", $policies)),
            ]);
            if (! $this->dryRun) {
                $this->files->ensureDirectoryExists(dirname($path));
                $this->files->put($path, $code);
            }
            $this->components->twoColumnDetail($relative, $this->status('created'));
            $this->registerProvider($provider);

            return;
        }

        $code = (string) $this->files->get($path);
        $original = $code;
        foreach ($classes as $index => $class) {
            if (str_contains($code, $class)) {
                continue;
            }
            $count = 0;
            $code = (string) preg_replace_callback(
                '/Panneau::resources\(\[([ \t]*\n?)/',
                // On their own lines, before the site's resources
                fn (array $match): string => "Panneau::resources([\n            \\".$class.'::class,'.(str_contains($match[1], "\n") ? "\n" : "\n            "),
                $code,
                1,
                $count,
            );
            if ($count === 0) {
                $this->todo[] = sprintf('Register %s in Panneau::resources() (%s).', $class, $relative);

                continue;
            }
            $policy = $policies[$index];
            $marker = "if (config('panneau.roles.enabled') === true) {";
            if (str_contains($code, $marker) && ! str_contains($code, $this->policyClass($resources[$index]))) {
                $code = str_replace($marker, $marker."\n            ".$policy, $code);
            }
        }
        if ($code === $original) {
            $this->components->twoColumnDetail($relative, '<fg=gray>registers them</>');

            return;
        }
        if (! $this->dryRun) {
            $this->files->put($path, $code);
        }
        $this->components->twoColumnDetail($relative, $this->status('updated'));
    }

    /**
     * Adds a provider to `bootstrap/providers.php`.
     */
    protected function registerProvider(string $provider): void
    {
        $path = $this->laravel->basePath('bootstrap/providers.php');
        if (! $this->files->exists($path)) {
            $this->todo[] = sprintf('Register %s with the site\'s service providers.', $provider);

            return;
        }
        $code = (string) $this->files->get($path);
        if (str_contains($code, $provider)) {
            return;
        }
        $position = strrpos($code, '];');
        if ($position === false) {
            $this->todo[] = sprintf('Register %s in bootstrap/providers.php.', $provider);

            return;
        }
        $before = rtrim(substr($code, 0, $position));
        $comma = str_ends_with($before, '[') || str_ends_with($before, ',') ? '' : ',';
        $code = $before.$comma."\n    ".$provider."::class,\n".substr($code, $position);
        if (! $this->dryRun) {
            $this->files->put($path, $code);
        }
        $this->components->twoColumnDetail('bootstrap/providers.php', $this->status('updated'));
    }

    /**
     * The registration of the policy of a resource.
     */
    protected function policy(string $resource): string
    {
        $item = match ($resource) {
            'pages' => '\\Folklore\\Contracts\\Entities\\Page::class',
            'users' => '\\Folklore\\Contracts\\Entities\\User::class',
            default => '\\Folklore\\Mediatheque\\Support\\Models::media()',
        };

        return 'Gate::policy('.$item.', \\'.$this->policyClass($resource).'::class);';
    }

    protected function policyClass(string $resource): string
    {
        return 'Folklore\\Panneau\\Policies\\'.ucfirst(substr($resource, 0, -1)).'Policy';
    }

    /**
     * Whether the class of a file extends a class: through the autoloader when it loads,
     * else from the `extends` of the file. Null when it can't tell.
     */
    protected function extendsBase(string $path, string $class, string $base): ?bool
    {
        try {
            if (class_exists($class) || interface_exists($class)) {
                return is_a($class, $base, true);
            }
        } catch (Throwable) {
            // A class that doesn't load: its file tells
        }

        $parents = $this->declaredParents((string) $this->files->get($path));

        return $parents === null ? null : in_array(ltrim($base, '\\'), $parents, true);
    }

    /**
     * The classes that the class or interface of a file extends, resolved with its
     * namespace and imports, or null without one.
     *
     * @return list<string>|null
     */
    protected function declaredParents(string $code): ?array
    {
        try {
            $tokens = array_values(array_filter(PhpToken::tokenize($code, TOKEN_PARSE), fn (PhpToken $token): bool => ! $token->isIgnorable()));
        } catch (Throwable) {
            return null;
        }
        $namespace = '';
        $imports = [];
        $parents = null;
        foreach ($tokens as $index => $token) {
            if ($token->is(T_NAMESPACE) && isset($tokens[$index + 1])) {
                $namespace = $tokens[$index + 1]->text;
            } elseif ($token->is(T_USE) && isset($tokens[$index + 1]) && $tokens[$index + 1]->is([T_NAME_QUALIFIED, T_STRING, T_NAME_FULLY_QUALIFIED])) {
                $name = ltrim($tokens[$index + 1]->text, '\\');
                $alias = isset($tokens[$index + 2], $tokens[$index + 3]) && $tokens[$index + 2]->is(T_AS) ? $tokens[$index + 3]->text : class_basename($name);
                $imports[$alias] = $name;
            } elseif ($token->is([T_CLASS, T_INTERFACE]) && ($index === 0 || ! $tokens[$index - 1]->is(T_DOUBLE_COLON))) {
                $parents = [];
                for ($i = $index + 1; isset($tokens[$i]) && $tokens[$i]->text !== '{'; $i++) {
                    if ($tokens[$i]->is(T_IMPLEMENTS)) {
                        break;
                    }
                    if ($tokens[$i]->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && isset($tokens[$i - 1]) && ($tokens[$i - 1]->is(T_EXTENDS) || $tokens[$i - 1]->text === ',')) {
                        $parents[] = $this->resolveName($tokens[$i]->text, $namespace, $imports);
                    }
                }

                break;
            }
        }

        return $parents;
    }

    /**
     * @param  array<string, string>  $imports
     */
    protected function resolveName(string $name, string $namespace, array $imports): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }
        $first = explode('\\', $name)[0];
        if (isset($imports[$first])) {
            return $imports[$first].substr($name, strlen($first));
        }

        return ltrim($namespace.'\\'.$name, '\\');
    }

    /**
     * The packages that a module needs and that aren't installed.
     *
     * @return list<string>
     */
    protected function missingPackages(string $module): array
    {
        $missing = [];
        foreach (FolkloreServiceProvider::MODULES[$module] ?? [] as $class => $package) {
            if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class)) {
                $missing[] = $package;
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * The code of a stub: the site's, published in `stubs/folklore/install`, or the
     * package's.
     *
     * @param  array<string, string>  $values
     */
    protected function stub(string $name, array $values): string
    {
        $custom = $this->laravel->basePath('stubs/folklore/install/'.$name.'.stub');
        $stub = (string) $this->files->get($this->files->exists($custom) ? $custom : __DIR__.'/../../stubs/install/'.$name.'.stub');
        foreach ($values as $key => $value) {
            $stub = str_replace(['{{ '.$key.' }}', '{{'.$key.'}}'], $value, $stub);
        }

        return $stub;
    }

    protected function rootNamespace(): string
    {
        return $this->laravel->getNamespace();
    }

    /**
     * The file of a class of the site's namespace.
     */
    protected function classPath(string $class): string
    {
        $relative = substr($class, strlen($this->rootNamespace()));

        return $this->laravel->path(str_replace('\\', '/', $relative).'.php');
    }

    protected function relative(string $path): string
    {
        $base = rtrim($this->laravel->basePath(), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    protected function status(string $done): string
    {
        $status = $this->dryRun ? 'would be '.$done : $done;

        return '<fg=green>'.$status.'</>';
    }
}
