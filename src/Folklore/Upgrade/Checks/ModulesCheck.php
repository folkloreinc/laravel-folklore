<?php

namespace Folklore\Upgrade\Checks;

use Folklore\ServiceProvider;
use Folklore\Upgrade\Finding;
use Folklore\Upgrade\ModuleUsage;
use Folklore\Upgrade\Site;

/**
 * The modules the site uses, and whether `config/site.php` turns them on: 1.1 booted
 * everything, 2.0 boots a module only when it is on. This covers what the 1.1 service
 * provider did for every site: the `routeForReactRouter`, `csv` and `pixel` macros, the
 * Customer.io client, the `repository` auth provider, the PubNub broadcaster, and the
 * commands of the modules, which the site's deployment and build files may run.
 */
class ModulesCheck implements Check
{
    public function title(): string
    {
        return 'Modules';
    }

    public function check(Site $site): iterable
    {
        $used = ModuleUsage::of($site);
        foreach (array_keys(ServiceProvider::MODULES) as $module) {
            if (in_array($module, RemovedModulesCheck::MODULES, true)) {
                continue;
            }
            $locations = $used[$module] ?? [];
            $enabled = $site->moduleEnabled($module);
            if (count($locations) > 0 && ! $enabled) {
                yield Finding::todo(
                    "The site uses the `{$module}` module, which is off: 2.0 boots it only when it is on.",
                    "Set `site.modules.{$module}` to `true` in config/site.php.",
                    $locations,
                );
            } elseif (count($locations) > 0) {
                yield Finding::info("The site uses the `{$module}` module, which is on.", null, $locations);
            } elseif ($enabled) {
                yield Finding::info("The `{$module}` module is on, and no use of it was found in the code read.");
            } elseif ($module === 'customerio' && ! is_null($site->config('services.customerio'))) {
                yield Finding::info(
                    '`services.customerio` is set, so 1.1 registered the Customer.io client, but no use of it was found in the code read.',
                    'Set `site.modules.customerio` to `true` if the site sends through it in a way this check misses.',
                );
            }
        }
    }
}
