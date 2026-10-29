<?php

namespace Folklore\Upgrade\Checks;

use Folklore\Upgrade\Finding;
use Folklore\Upgrade\Site;

/**
 * What 2.0 removes, which no active site used when it was decided (decisions/0012): the
 * site manager, organisations, PubSubHubbub, the contracts that nothing implements, and
 * Panneau 1.x's fields and resources.
 */
class RemovedModulesCheck implements Check
{
    /**
     * The modules of `config/site.php` that 2.0 removes.
     *
     * @var list<string>
     */
    public const MODULES = ['organisations', 'sites', 'pubsubhubbub'];

    /**
     * What is removed, with its classes or namespaces (ending with a backslash).
     *
     * @var array<string, list<string>>
     */
    public const REMOVED = [
        'The site manager' => [
            'Folklore\\Site\\',
            'Folklore\\Contracts\\Site\\',
        ],
        'Organisations' => [
            'Folklore\\Repositories\\Organisations',
            'Folklore\\Contracts\\Repositories\\Organisations',
            'Folklore\\Models\\Organisation',
            'Folklore\\Models\\OrganisationInvitation',
            'Folklore\\Models\\OrganisationMember',
            'Folklore\\Entities\\Organisation',
            'Folklore\\Entities\\OrganisationInvitation',
            'Folklore\\Entities\\OrganisationMember',
            'Folklore\\Contracts\\Entities\\Organisation',
            'Folklore\\Contracts\\Entities\\OrganisationInvitation',
            'Folklore\\Contracts\\Entities\\OrganisationMember',
        ],
        'PubSubHubbub' => [
            'Folklore\\Services\\PubSubHubbub\\',
            'Folklore\\Contracts\\Services\\PubSubHubbub\\',
            'Folklore\\Console\\PubSubHubbubSubscribe',
            'Folklore\\Console\\PubSubHubbubUnsubscribe',
        ],
        'The `Contact`, `Person` and `Contactable` contracts' => [
            'Folklore\\Contracts\\Entities\\Contact',
            'Folklore\\Contracts\\Entities\\Person',
            'Folklore\\Contracts\\Entities\\Contactable',
        ],
        'Panneau 1.x\'s fields and resources' => [
            'Folklore\\Panneau\\',
        ],
    ];

    public function title(): string
    {
        return 'Removed modules';
    }

    public function check(Site $site): iterable
    {
        foreach (self::REMOVED as $what => $classes) {
            $locations = [];
            foreach ($classes as $class) {
                foreach ($site->references($class) as $occurrence) {
                    $locations[] = $occurrence->location().' ('.rtrim($class, '\\').')';
                }
            }
            if (count($locations) > 0) {
                yield Finding::breaking(
                    "{$what}: removed in 2.0.",
                    $what === 'Panneau 1.x\'s fields and resources'
                        ? 'Move the admin to Panneau 5 and the plugin of 2.0.'
                        : 'Move the code into the application, or keep the site on 1.1.',
                    $locations,
                );
            }
        }

        foreach (self::MODULES as $module) {
            if ($site->moduleEnabled($module)) {
                yield Finding::breaking(
                    "`site.modules.{$module}` is on, and the module is removed in 2.0.",
                    "Remove `{$module}` from `site.modules`.",
                );
            }
        }
        if (! is_null($site->config('pubsubhubbub'))) {
            yield Finding::info('The `pubsubhubbub` config is set: PubSubHubbub is removed in 2.0, so it is no longer read.');
        }
    }
}
