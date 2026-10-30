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
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;

/**
 * Panneau 1.x's `ResourceItem` and `Repository` → Folklore's `Entity` and `Entities`
 * contracts, which no longer extend them, where they type a value: parameters, return
 * and property types, `instanceof`. A `::class` constant is left as it is, since a
 * container binding (`bind(Repository::class, ...)`) would become a binding of Folklore's
 * contract: `folklore:upgrade --check` reports it.
 *
 * In the `extends` of an interface and the `implements` of a class, the Panneau contract is
 * removed when Folklore's is already there (`interface Order extends Entity, ResourceItem`),
 * through another interface or the parent class. Otherwise `ResourceItem` is replaced by
 * `Entity`, which declares the same method, and `Repository` stays: `Entities` asks for more
 * methods, with other signatures, which the classes that implement it may not have. In a
 * class or an interface that is still a `Repository`, the methods of that contract keep their
 * types, which must stay compatible with its `ResourceItem` while Panneau 1.x is installed.
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
     * The Panneau contracts whose Folklore contract declares the same methods, so that it can
     * replace them in `extends` and `implements`.
     *
     * @var list<string>
     */
    public const SAME_METHODS = ['Panneau\\Contracts\\ResourceItem'];

    /**
     * The methods of Panneau 1.x's `Repository`.
     *
     * @var list<string>
     */
    public const REPOSITORY_METHODS = ['findById', 'get', 'create', 'update', 'destroy'];

    /**
     * The attribute of the methods, and their parameters, whose types stay.
     */
    protected const KEPT = 'folklore_panneau_repository_method';

    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Param::class, ClassMethod::class, Function_::class, Closure::class, ArrowFunction::class, Property::class, Instanceof_::class, Class_::class, Interface_::class];
    }

    /**
     * @param  Param|ClassMethod|Function_|Closure|ArrowFunction|Property|Instanceof_|Class_|Interface_  $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Interface_) {
            $extends = $this->replaceParents($node->extends, []);
            if (! is_null($extends)) {
                $node->extends = $extends;
            }
            $this->keepRepositoryMethods($node, $node->extends);

            return is_null($extends) ? null : $node;
        }
        if ($node instanceof Class_) {
            $parents = is_null($node->extends) ? [] : [$node->extends];
            $implements = $this->replaceParents($node->implements, $parents);
            if (! is_null($implements)) {
                $node->implements = $implements;
            }
            $this->keepRepositoryMethods($node, [...$node->implements, ...$parents]);

            return is_null($implements) ? null : $node;
        }
        if (($node instanceof ClassMethod || $node instanceof Param) && $node->getAttribute(self::KEPT) === true) {
            return null;
        }
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
     * The interfaces that a class implements or an interface extends, without the Panneau
     * contracts that the other interfaces or the parents already give, and with Folklore's
     * contract instead of those that declare the same methods. Null when nothing changes.
     *
     * @param  array<Name>  $interfaces
     * @param  array<Name>  $parents  The parent class of a class.
     * @return list<Name>|null
     */
    protected function replaceParents(array $interfaces, array $parents): ?array
    {
        $others = array_values(array_filter($interfaces, fn (Name $name) => is_null($this->rename($name))));

        $replaced = [];
        $changed = false;
        foreach ($interfaces as $name) {
            $replacement = $this->rename($name);
            if (is_null($replacement)) {
                $replaced[] = $name;
            } elseif ($this->gives([...$others, ...$replaced, ...$parents], $replacement->toString())) {
                $changed = true;
            } elseif ($this->isNames($name, self::SAME_METHODS)) {
                $replaced[] = $replacement;
                $changed = true;
            } else {
                $replaced[] = $name;
            }
        }

        return $changed ? $replaced : null;
    }

    /**
     * Marks the methods of Panneau 1.x's `Repository`, and their parameters, in a class or an
     * interface that is still one, through its parents: their types stay. A class or an
     * interface is visited before its methods.
     *
     * @param  array<Name>  $parents
     */
    protected function keepRepositoryMethods(Class_|Interface_ $node, array $parents): void
    {
        if (! $this->gives($parents, 'Panneau\\Contracts\\Repository')) {
            return;
        }
        foreach ($node->getMethods() as $method) {
            if ($this->isNames($method, self::REPOSITORY_METHODS)) {
                $method->setAttribute(self::KEPT, true);
                foreach ($method->params as $param) {
                    $param->setAttribute(self::KEPT, true);
                }
            }
        }
    }

    /**
     * Whether one of the names is the interface, or a class or an interface that extends it.
     *
     * @param  array<Name>  $names
     */
    protected function gives(array $names, string $interface): bool
    {
        foreach ($names as $name) {
            if ($this->isName($name, $interface)) {
                return true;
            }
            $class = $this->getName($name);
            if ($this->reflectionProvider->hasClass($class) && $this->reflectionProvider->getClass($class)->isSubclassOf($interface)) {
                return true;
            }
        }

        return false;
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
