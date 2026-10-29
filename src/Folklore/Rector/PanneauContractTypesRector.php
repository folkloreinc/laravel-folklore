<?php

namespace Folklore\Rector;

use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use Rector\Rector\AbstractRector;

/**
 * Panneau 1.x's `ResourceItem` and `Repository` → Folklore's `Entity` and `Entities`
 * contracts, which no longer extend them, where they type a value: parameters, return
 * and property types, `instanceof`. A `::class` constant is left as it is, since a
 * container binding (`bind(Repository::class, ...)`) would become a binding of Folklore's
 * contract: `folklore:upgrade --check` reports it.
 */
final class PanneauContractTypesRector extends AbstractRector
{
    /**
     * @var array<string, string>
     */
    public const CONTRACTS = [
        'Panneau\\Contracts\\ResourceItem' => 'Folklore\\Contracts\\Entities\\Entity',
        'Panneau\\Contracts\\Repository' => 'Folklore\\Contracts\\Repositories\\Entities',
    ];

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Param::class, ClassMethod::class, Function_::class, Closure::class, ArrowFunction::class, Property::class, Instanceof_::class];
    }

    /**
     * @param  Param|ClassMethod|Function_|Closure|ArrowFunction|Property|Instanceof_  $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Instanceof_) {
            if (! $node->class instanceof Name) {
                return null;
            }
            $renamed = $this->rename($node->class);
            if (is_null($renamed)) {
                return null;
            }
            $node->class = $renamed;

            return $node;
        }

        $type = $node instanceof Param || $node instanceof Property ? $node->type : $node->returnType;
        $renamed = is_null($type) ? null : $this->renameType($type);
        if (is_null($renamed)) {
            return null;
        }
        if ($node instanceof Param || $node instanceof Property) {
            $node->type = $renamed;
        } else {
            $node->returnType = $renamed;
        }

        return $node;
    }

    /**
     * The type with the contracts renamed, or null when it names none.
     */
    protected function renameType(Identifier|Name|ComplexType $type): Name|ComplexType|null
    {
        if ($type instanceof Name) {
            return $this->rename($type);
        }
        if ($type instanceof NullableType && $type->type instanceof Name) {
            $renamed = $this->rename($type->type);
            if (is_null($renamed)) {
                return null;
            }
            $type->type = $renamed;

            return $type;
        }
        if ($type instanceof UnionType) {
            $changed = false;
            foreach ($type->types as $index => $member) {
                $renamed = $member instanceof Identifier ? null : $this->renameType($member);
                if ($renamed instanceof Name || $renamed instanceof IntersectionType) {
                    $type->types[$index] = $renamed;
                    $changed = true;
                }
            }

            return $changed ? $type : null;
        }
        if ($type instanceof IntersectionType) {
            $changed = false;
            foreach ($type->types as $index => $member) {
                $renamed = $member instanceof Name ? $this->rename($member) : null;
                if (! is_null($renamed)) {
                    $type->types[$index] = $renamed;
                    $changed = true;
                }
            }

            return $changed ? $type : null;
        }

        return null;
    }

    protected function rename(Name $name): ?FullyQualified
    {
        foreach (self::CONTRACTS as $old => $new) {
            if ($this->isName($name, $old)) {
                return new FullyQualified($new);
            }
        }

        return null;
    }
}
