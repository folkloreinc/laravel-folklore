<?php

namespace Folklore\Upgrade\Checks;

use Folklore\Upgrade\Finding;
use Folklore\Upgrade\Occurrence;
use Folklore\Upgrade\Site;

/**
 * The 1.x API that 2.0 keeps, deprecated until 3.0.
 */
class DeprecatedApiCheck implements Check
{
    /**
     * [pattern, what, fix].
     *
     * @var list<array{string, string, string}>
     */
    public const DEPRECATED = [
        [
            '/JsonDataCast::macro\s*\(/',
            '`JsonDataCast::macro()`: the 1.x macros of the cast keep working, deprecated.',
            'Write a reference handler (`Folklore\\JsonData\\Contracts\\ReferenceHandler`), and map its key with `JsonData::map()` (docs/json-data.md).',
        ],
        [
            '/\$jsonAttributeName\b/',
            '`$jsonAttributeName` on a repository: kept, deprecated.',
            'List the JSON columns in `$jsonAttributes`.',
        ],
        [
            '/->setGlobalQuery\s*\(/',
            '`setGlobalQuery()` changes the repository it is called on: kept, deprecated.',
            'Use `withGlobalQuery()`, which returns a copy, or a `QueryContextResolver`.',
        ],
    ];

    public function title(): string
    {
        return 'Deprecated API';
    }

    public function check(Site $site): iterable
    {
        foreach (self::DEPRECATED as [$pattern, $what, $fix]) {
            $locations = array_map(fn (Occurrence $occurrence) => $occurrence->location(), $site->matches($pattern));
            if (count($locations) > 0) {
                yield Finding::deprecated($what, $fix, $locations);
            }
        }
    }
}
