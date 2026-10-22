<?php

namespace Folklore\Tests\Unit\Contracts;

use Folklore\Contracts\Entities\Block;
use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\MediaFile;
use Folklore\Contracts\Entities\Page;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Contracts\Repositories\Entities;
use Folklore\Contracts\Repositories\QueriesEntities;
use Folklore\Contracts\Repositories\QueryContextResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The core contracts don't depend on Panneau, the mediatheque or laravel-image
 * (docs/decisions/0002 and 0003).
 */
class CoreContractsTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function contracts(): array
    {
        return [
            'Entity' => [Entity::class],
            'HasModel' => [HasModel::class],
            'ToEntity' => [ToEntity::class],
            'Entities' => [Entities::class],
            'QueriesEntities' => [QueriesEntities::class],
            'QueryContextResolver' => [QueryContextResolver::class],
            'Page' => [Page::class],
            'Block' => [Block::class],
            'MediaFile' => [MediaFile::class],
        ];
    }

    /**
     * @param  class-string  $contract
     */
    #[DataProvider('contracts')]
    public function test_a_contract_extends_no_panneau_contract(string $contract)
    {
        $parents = (new ReflectionClass($contract))->getInterfaceNames();

        $this->assertSame([], array_values(array_filter(
            $parents,
            fn (string $parent): bool => str_starts_with($parent, 'Panneau\\'),
        )));
    }

    public function test_the_core_contracts_only_use_php_laravel_and_this_package()
    {
        $core = [Entity::class, HasModel::class, ToEntity::class, Entities::class, QueriesEntities::class, QueryContextResolver::class];
        foreach ($core as $contract) {
            $file = (string) (new ReflectionClass($contract))->getFileName();
            preg_match_all('/^use ([^;]+);/m', (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $import) {
                $this->assertMatchesRegularExpression('/^(Folklore\\\\(?!Mediatheque|Image|Locale)|Illuminate\\\\)/', $import, "{$contract} imports {$import}.");
            }
        }
    }
}
