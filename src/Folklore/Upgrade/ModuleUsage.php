<?php

namespace Folklore\Upgrade;

/**
 * Which modules a 1.1 site uses, from its code and config: 1.1 booted everything, 2.0
 * boots a module only when `config/site.php` turns it on.
 */
class ModuleUsage
{
    /**
     * The classes, or namespaces when they end with a backslash, whose use means a module.
     *
     * @var array<string, list<string>>
     */
    public const CLASSES = [
        'medias' => [
            'Folklore\\Repositories\\Medias',
            'Folklore\\Contracts\\Repositories\\Medias',
            'Folklore\\Models\\Media',
            'Folklore\\Models\\MediaFile',
            'Folklore\\Entities\\Media',
            'Folklore\\Entities\\Image',
            'Folklore\\Entities\\ImageSize',
            'Folklore\\Entities\\Video',
            'Folklore\\Entities\\Audio',
            'Folklore\\Entities\\Document',
            'Folklore\\Entities\\MediaFile',
            'Folklore\\Contracts\\Entities\\Media',
            'Folklore\\Contracts\\Entities\\Image',
            'Folklore\\Contracts\\Entities\\ImageSize',
            'Folklore\\Contracts\\Entities\\Video',
            'Folklore\\Contracts\\Entities\\Audio',
            'Folklore\\Contracts\\Entities\\Document',
            'Folklore\\Contracts\\Entities\\MediaFile',
            'Folklore\\Http\\Resources\\MediaResource',
            'Folklore\\Http\\Resources\\MediasCollection',
            'Folklore\\Eloquent\\MediaReferences',
        ],
        'pages' => [
            'Folklore\\Repositories\\Pages',
            'Folklore\\Repositories\\Blocks',
            'Folklore\\Contracts\\Repositories\\Pages',
            'Folklore\\Contracts\\Repositories\\Blocks',
            'Folklore\\Models\\Page',
            'Folklore\\Models\\Block',
            'Folklore\\Entities\\Page',
            'Folklore\\Entities\\Block',
            'Folklore\\Entities\\PageMetadata',
            'Folklore\\Contracts\\Entities\\Page',
            'Folklore\\Contracts\\Entities\\Block',
            'Folklore\\Contracts\\Entities\\PageMetadata',
            'Folklore\\Contracts\\Entities\\HasBlocks',
            'Folklore\\Contracts\\Entities\\Pageable',
        ],
        'auth' => [
            'Folklore\\Repositories\\Users',
            'Folklore\\Contracts\\Repositories\\Users',
            'Folklore\\Models\\User',
            'Folklore\\Entities\\User',
            'Folklore\\Contracts\\Entities\\User',
        ],
        'http' => [
            'Folklore\\Support\\OffsetPaginator',
        ],
        'assets' => [
            'Folklore\\Http\\Middleware\\LocalMiddleware',
        ],
        'customerio' => [
            'Folklore\\Contracts\\Services\\CustomerIo',
            'Folklore\\Contracts\\Services\\CustomerIo\\',
            'Folklore\\Services\\CustomerIo\\',
            'Folklore\\Notifications\\',
        ],
        'google' => [
            'Folklore\\Services\\Google\\',
            'Folklore\\Contracts\\Services\\Google\\',
        ],
        'pubnub' => [
            'Folklore\\Broadcasters\\',
        ],
    ];

    /**
     * The lines whose pattern means a module: [pattern, paths it is looked for in, what it is].
     *
     * @var array<string, list<array{string, list<string>, string}>>
     */
    public const PATTERNS = [
        'auth' => [
            ['/[\'"]driver[\'"]\s*=>\s*[\'"]repository[\'"]/', ['config/auth.php'], 'the `repository` auth provider'],
        ],
        'http' => [
            ['/\brouteForReactRouter\s*\(/', [], 'the `routeForReactRouter` URL macro'],
            ['/(?:response\(\)|Response::|ResponseFactory)\s*->?\s*csv\s*\(|Response::csv\s*\(/', [], 'the `csv` response macro'],
            ['/(?:response\(\)\s*->|Response::)\s*pixel\s*\(/', [], 'the `pixel` response macro'],
        ],
        'customerio' => [
            ['/[\'"]services\.customerio[\'"]/', [], 'the `services.customerio` binding'],
            ['/[\'"]transport[\'"]\s*=>\s*[\'"]customerio[\'"]/', ['config/mail.php'], 'the `customerio` mail transport'],
        ],
        'pubnub' => [
            ['/[\'"]driver[\'"]\s*=>\s*[\'"]pubnub[\'"]/', ['config/broadcasting.php'], 'the `pubnub` broadcast driver'],
        ],
    ];

    /**
     * The commands that 2.0 registers only with their module, called from the site's code
     * (`Artisan::call('assets:view')`) or run by its deployment and build files
     * (`php artisan assets:view` in `Envoy.blade.php`).
     *
     * @var array<string, list<string>>
     */
    public const COMMANDS = [
        'auth' => ['users:create'],
        'assets' => ['assets:view'],
    ];

    /**
     * Macros of the http module that a site may define itself: its own definition
     * replaces the module's.
     *
     * @var array<string, string>
     */
    public const OWN_MACROS = [
        'the `routeForReactRouter` URL macro' => 'routeForReactRouter',
        'the `csv` response macro' => 'csv',
        'the `pixel` response macro' => 'pixel',
    ];

    /**
     * The modules the site uses, each with where: `path:line (what)`.
     *
     * @return array<string, list<string>>
     */
    public static function of(Site $site): array
    {
        $used = [];
        foreach (self::CLASSES as $module => $classes) {
            foreach ($classes as $class) {
                foreach ($site->references($class) as $occurrence) {
                    $used[$module][] = $occurrence->location().' ('.rtrim($class, '\\').')';
                }
            }
        }
        foreach (self::PATTERNS as $module => $patterns) {
            foreach ($patterns as [$pattern, $in, $what]) {
                $macro = self::OWN_MACROS[$what] ?? null;
                if (! is_null($macro) && count($site->filesMatching('/macro\(\s*[\'"]'.$macro.'[\'"]/')) > 0) {
                    continue;
                }
                foreach ($site->matches($pattern, $in) as $occurrence) {
                    $used[$module][] = $occurrence->location().' ('.$what.')';
                }
            }
        }

        foreach (self::COMMANDS as $module => $commands) {
            foreach ($commands as $command) {
                $name = preg_quote($command, '/');
                $occurrences = [
                    ...$site->matches('/[\'"]'.$name.'(?![\w:-])/'),
                    ...$site->deploymentMatches('/(?<![\w:-])'.$name.'(?![\w:-])/'),
                ];
                foreach ($occurrences as $occurrence) {
                    $used[$module][] = $occurrence->location()." (the `{$command}` command)";
                }
            }
        }

        return array_map(fn (array $locations) => array_values(array_unique($locations)), $used);
    }
}
