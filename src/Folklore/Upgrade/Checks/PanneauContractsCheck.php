<?php

namespace Folklore\Upgrade\Checks;

use Folklore\Upgrade\Finding;
use Folklore\Upgrade\Site;

/**
 * Site code that uses Panneau 1.x's contracts through Folklore's: in 1.1, `Entity`
 * extends `Panneau\Contracts\ResourceItem` and `Entities` extends
 * `Panneau\Contracts\Repository`. 2.0 drops both (decisions/0003), so a type hint or an
 * `instanceof` on them no longer matches Folklore's entities and repositories.
 */
class PanneauContractsCheck implements Check
{
    /**
     * Panneau's contracts, with Folklore's contract that replaces them.
     *
     * @var array<string, string>
     */
    public const CONTRACTS = [
        'Panneau\\Contracts\\ResourceItem' => 'Folklore\\Contracts\\Entities\\Entity',
        'Panneau\\Contracts\\Repository' => 'Folklore\\Contracts\\Repositories\\Entities',
    ];

    /**
     * What the Rector set does with each contract.
     *
     * @var array<string, string>
     */
    public const RECTOR = [
        'Panneau\\Contracts\\ResourceItem' => 'The Rector set rewrites it in types and `instanceof`, and in `extends` and `implements`, where it removes it when `Entity` is already there.',
        'Panneau\\Contracts\\Repository' => 'The Rector set rewrites it in types and `instanceof`, and removes it from `extends` and `implements` where `Entities` is already there. Elsewhere it stays: `Entities` asks for more methods, with other signatures, which the classes that implement it must have first.',
    ];

    public function title(): string
    {
        return 'Panneau contracts';
    }

    public function check(Site $site): iterable
    {
        foreach (self::CONTRACTS as $contract => $replacement) {
            $locations = array_map(fn ($occurrence) => $occurrence->location(), $site->references($contract));
            if (count($locations) > 0) {
                yield Finding::breaking(
                    "`{$contract}`: Folklore's entities and repositories no longer implement it in 2.0, so a type hint or an `instanceof` on it no longer matches them.",
                    "Use `{$replacement}`; in Panneau 5, the plugin's adapter. ".self::RECTOR[$contract].' A `use` of it that the Rector set leaves unused is reported until it is removed.',
                    $locations,
                );
            }
        }
    }
}
