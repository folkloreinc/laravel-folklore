<?php

namespace Folklore\Upgrade\Checks;

use Folklore\Upgrade\Finding;
use Folklore\Upgrade\Occurrence;
use Folklore\Upgrade\Site;

/**
 * What 2.0 removes, which no active site used when it was decided (decisions/0012): the
 * site manager, organisations, PubSubHubbub, the contracts that nothing implements, and
 * Panneau 1.x's fields and resources. The classes of 2.0's Panneau plugin, in the same
 * namespace, aren't removed: those named as 1.x's fields are Panneau 5 fields, which the
 * 1.x admin can't use. Their `PageSlug::setRoutesResolver()`, which the 1.x admin calls at
 * boot, is kept while laravel-panneau 1.x is installed, so that the site boots.
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

    /**
     * The classes of 2.0's Panneau plugin, which `Folklore\Panneau\` doesn't remove.
     *
     * @var list<string>
     */
    public const PANNEAU_PLUGIN = [
        'Folklore\\Panneau\\BlockTypes',
        'Folklore\\Panneau\\EntitiesRepository',
        'Folklore\\Panneau\\PanneauServiceProvider',
        'Folklore\\Panneau\\SearchableEntitiesRepository',
        'Folklore\\Panneau\\Fields\\Blocks',
        'Folklore\\Panneau\\Fields\\LegacyPageSlug',
        'Folklore\\Panneau\\Fields\\Page',
        'Folklore\\Panneau\\Fields\\Pages',
        'Folklore\\Panneau\\Fields\\PageSlug',
    ];

    /**
     * Panneau 1.x's fields whose names 2.0 gives to Panneau 5 fields.
     *
     * @var list<string>
     */
    public const PANNEAU_1X_FIELDS = [
        'Folklore\\Panneau\\Fields\\Blocks',
        'Folklore\\Panneau\\Fields\\Page',
        'Folklore\\Panneau\\Fields\\PageSlug',
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
                    if ($class === 'Folklore\\Panneau\\' && $this->onlyNamesThePlugin($occurrence)) {
                        continue;
                    }
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

        if (! $this->hasPanneau5()) {
            $locations = [];
            foreach (self::PANNEAU_1X_FIELDS as $class) {
                foreach ($site->references($class) as $occurrence) {
                    $locations[] = $occurrence->location().' ('.$class.')';
                }
            }
            if (count($locations) > 0) {
                yield Finding::breaking(
                    'Panneau 1.x\'s `Blocks`, `Page` and `PageSlug` fields: in 2.0, these classes are Panneau 5 fields, with another API.',
                    'Move the admin to Panneau 5 and the plugin of 2.0 (docs/upgrade.md, "Panneau 5").',
                    $locations,
                );
            }
        }

        $resolvers = array_map(fn (Occurrence $occurrence) => $occurrence->location(), $site->matches('/::setRoutesResolver\s*\(/'));
        if (count($resolvers) > 0) {
            yield Finding::todo(
                '`PageSlug::setRoutesResolver()`: 1.x\'s routes of the page slug field. While laravel-panneau 1.x is installed, 2.0 keeps the method so that the site boots, but it does nothing; Panneau 5\'s `PageSlug` doesn\'t have it.',
                'Remove the call when the admin moves to Panneau 5: its `PageSlug` builds the URL from `site.pages.routes`; check that they match the site\'s routes, or set `url()` on the field.',
                $resolvers,
            );
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

    /**
     * Whether Panneau 5 is installed: without it, a reference to a class of the plugin is
     * the 1.x admin's.
     */
    protected function hasPanneau5(): bool
    {
        return class_exists('Panneau\\Fields\\Slug');
    }

    /**
     * Whether the classes of `Folklore\Panneau\` that a line names are all the plugin's.
     */
    protected function onlyNamesThePlugin(Occurrence $occurrence): bool
    {
        $line = $occurrence->file->lines[$occurrence->line] ?? '';
        foreach (self::PANNEAU_PLUGIN as $class) {
            $line = (string) preg_replace(Site::referencePattern($class), '', $line);
        }

        return preg_match(Site::referencePattern('Folklore\\Panneau\\'), $line) !== 1;
    }
}
