<?php

namespace Folklore\Upgrade\Checks;

use Folklore\ServiceProvider;
use Folklore\Upgrade\Finding;
use Folklore\Upgrade\ModuleUsage;
use Folklore\Upgrade\Site;

/**
 * The packages that 1.1 required and 2.0 only suggests: a site that uses them requires
 * them itself, or Composer removes them when it updates.
 */
class PackagesCheck implements Check
{
    /**
     * The packages that features outside the module checks need: [class or namespace,
     * package, what].
     *
     * @var list<array{string, string, string}>
     */
    public const FEATURES = [
        ['Folklore\\Composers\\', 'folklore/laravel-locale', 'the view composers, which read `locale.locales`'],
        ['Folklore\\Services\\Google\\Places', 'google/apiclient', 'Google Places'],
        ['Folklore\\Services\\Google\\Drive', 'parsecsv/php-parsecsv', 'the CSV export of Google Drive'],
        ['Folklore\\Services\\CustomerIo\\', 'guzzlehttp/guzzle', 'the Customer.io client'],
        ['Folklore\\Services\\Google\\', 'guzzlehttp/guzzle', 'the Google services'],
    ];

    public function title(): string
    {
        return 'Packages';
    }

    public function check(Site $site): iterable
    {
        if (! $site->hasComposerFile()) {
            yield Finding::info('No composer.json was found at the base path: the packages the site requires were not checked.');

            return;
        }

        $required = $site->requiredPackages();
        $needed = [];
        $used = ModuleUsage::of($site);
        foreach (ServiceProvider::MODULES as $module => $requirements) {
            if (! $site->moduleEnabled($module) && ! isset($used[$module])) {
                continue;
            }
            foreach ($requirements as $package) {
                [$name] = explode(' ', $package);
                $needed[$name][] = "the `{$module}` module";
            }
        }
        foreach (self::FEATURES as [$class, $package, $what]) {
            if (count($site->references($class)) > 0) {
                $needed[$package][] = $what;
            }
        }

        foreach ($needed as $package => $reasons) {
            if (! in_array($package, $required, true)) {
                $reasons = array_values(array_unique($reasons));
                yield Finding::todo(
                    "The site doesn't require `{$package}`, which 1.1 required and 2.0 only suggests. It needs it for ".implode('; ', $reasons).'.',
                    "composer require {$package}",
                );
            }
        }
    }
}
