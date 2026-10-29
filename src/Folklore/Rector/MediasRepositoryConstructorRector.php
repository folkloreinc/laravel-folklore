<?php

namespace Folklore\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use Rector\Rector\AbstractRector;

/**
 * `new Medias($typeFactory)` → `new Medias()`: the medias repository of 2.0 takes no
 * argument.
 */
final class MediasRepositoryConstructorRector extends AbstractRector
{
    public const REPOSITORY = 'Folklore\\Repositories\\Medias';

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [New_::class];
    }

    /**
     * @param  New_  $node
     */
    public function refactor(Node $node): ?Node
    {
        if (count($node->args) === 0 || ! $this->isName($node->class, self::REPOSITORY)) {
            return null;
        }

        $node->args = [];

        return $node;
    }
}
