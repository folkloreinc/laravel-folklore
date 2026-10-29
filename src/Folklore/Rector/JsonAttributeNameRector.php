<?php

namespace Folklore\Rector;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\VarLikeIdentifier;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;

/**
 * `protected $jsonAttributeName = 'data'` → `protected $jsonAttributes = ['data']` in a
 * repository of the site. The first one is kept next to the second, which wins, when a
 * subclass may read it: unless the repository is final and doesn't read it itself. It
 * is kept, deprecated, until 3.0.
 */
final class JsonAttributeNameRector extends AbstractRector
{
    public const REPOSITORY = 'Folklore\\Repositories\\Entities';

    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param  Class_  $node
     */
    public function refactor(Node $node): ?Node
    {
        $name = $this->getName($node);
        if (is_null($name) || ! $this->reflectionProvider->hasClass($name) || ! $this->reflectionProvider->getClass($name)->isSubclassOf(self::REPOSITORY)) {
            return null;
        }
        if (! is_null($node->getProperty('jsonAttributes'))) {
            return null;
        }
        $property = $node->getProperty('jsonAttributeName');
        $default = $property?->props[0]->default ?? null;
        if (is_null($property) || count($property->props) !== 1 || ! $default instanceof String_) {
            return null;
        }

        $read = false;
        $this->traverseNodesWithCallable($node->stmts, function (Node $child) use (&$read) {
            if (($child instanceof PropertyFetch || $child instanceof StaticPropertyFetch) && $this->isName($child->name, 'jsonAttributeName')) {
                $read = true;
            }

            return null;
        });

        $attributes = new Property($property->flags, [
            new PropertyItem(new VarLikeIdentifier('jsonAttributes'), new Array_([new ArrayItem(clone $default)], ['kind' => Array_::KIND_SHORT])),
        ]);
        if (! $node->isFinal() || $read) {
            // A subclass, or the class itself, may read `$this->jsonAttributeName`: it stays
            $position = array_search($property, $node->stmts, true);
            array_splice($node->stmts, is_int($position) ? $position + 1 : count($node->stmts), 0, [$attributes]);

            return $node;
        }

        $item = $property->props[0];
        $item->name = new VarLikeIdentifier('jsonAttributes');
        $item->default = new Array_([new ArrayItem($default)], ['kind' => Array_::KIND_SHORT]);

        return $node;
    }
}
