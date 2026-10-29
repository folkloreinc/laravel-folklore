<?php

namespace Folklore\Upgrade;

use Folklore\Upgrade\Checks\BlocksCheck;
use Folklore\Upgrade\Checks\Check;
use Folklore\Upgrade\Checks\DeprecatedApiCheck;
use Folklore\Upgrade\Checks\JsonCodeCheck;
use Folklore\Upgrade\Checks\MediaResourceCheck;
use Folklore\Upgrade\Checks\MediasCheck;
use Folklore\Upgrade\Checks\ModulesCheck;
use Folklore\Upgrade\Checks\PackagesCheck;
use Folklore\Upgrade\Checks\PanneauContractsCheck;
use Folklore\Upgrade\Checks\RemovedModulesCheck;
use Folklore\Upgrade\Checks\StoredReferencesCheck;

/**
 * What `folklore:upgrade --check` reports about a 1.1 site: each check, with its findings.
 */
class UpgradeCheck
{
    /**
     * @var list<class-string<Check>>
     */
    public const CHECKS = [
        ModulesCheck::class,
        RemovedModulesCheck::class,
        PackagesCheck::class,
        PanneauContractsCheck::class,
        JsonCodeCheck::class,
        StoredReferencesCheck::class,
        BlocksCheck::class,
        MediasCheck::class,
        MediaResourceCheck::class,
        DeprecatedApiCheck::class,
    ];

    /**
     * @param  list<Check>|null  $checks  Null: the checks of CHECKS.
     */
    public function __construct(protected ?array $checks = null) {}

    /**
     * The findings of each check, by its title, in order.
     *
     * @return array<string, list<Finding>>
     */
    public function run(Site $site): array
    {
        $report = [];
        foreach ($this->checks ?? array_map(fn (string $check) => new $check, self::CHECKS) as $check) {
            $report[$check->title()] = array_values([...$check->check($site)]);
        }

        return $report;
    }
}
