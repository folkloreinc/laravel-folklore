<?php

namespace Folklore\Rector;

use Folklore\Upgrade\Checks\MediaResourceCheck;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;

/**
 * A subclass of `Folklore\Http\Resources\MediaResource` written for 1.1, where the resource
 * was the entity: in 2.0, it extends laravel-mediatheque's resource, whose resource is
 * the media model. `toArray($request)` gets its types, and the entity is called through
 * `entity()`: `$this->files()` → `$this->entity()->files()`, `$this->resource` →
 * `$this->entity()`.
 */
final class MediaResourceSubclassRector extends AbstractRector
{
    public const JSON_RESOURCE = 'Illuminate\\Http\\Resources\\Json\\JsonResource';

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
        if (is_null($name) || ! $this->reflectionProvider->hasClass($name)) {
            return null;
        }
        $class = $this->reflectionProvider->getClass($name);
        if (! $class->isSubclassOf(MediaResourceCheck::RESOURCE)) {
            return null;
        }

        $methods = $this->ownMethods($name);
        $changed = false;

        $toArray = $node->getMethod('toArray');
        if ($toArray instanceof ClassMethod) {
            if (is_null($toArray->returnType)) {
                $toArray->returnType = new Identifier('array');
                $changed = true;
            }
            if (isset($toArray->params[0]) && is_null($toArray->params[0]->type)) {
                $toArray->params[0]->type = new FullyQualified('Illuminate\\Http\\Request');
                $changed = true;
            }
        }

        // `$this->resource = ...` stays: only reads of the resource are the entity
        $assigned = [];
        $this->traverseNodesWithCallable($node->stmts, function (Node $child) use ($methods, &$assigned, &$changed) {
            if ($child instanceof Assign && $this->isResource($child->var)) {
                $assigned[] = spl_object_id($child->var);

                return null;
            }
            if ($this->isResource($child) && ! in_array(spl_object_id($child), $assigned, true)) {
                $changed = true;

                return $this->entity();
            }
            if ($child instanceof MethodCall && $this->isThis($child->var) && $child->name instanceof Identifier
                && ! in_array(strtolower($child->name->toString()), $methods, true)) {
                $changed = true;
                $child->var = $this->entity();

                return $child;
            }

            return null;
        });

        return $changed ? $node : null;
    }

    /**
     * The methods that a 1.1 subclass called on itself rather than on the entity: those of
     * `JsonResource`, of 1.1's `MediaResource`, those the site's classes declare between
     * it and the subclass, and those that 2.0's adds. Lower case.
     *
     * @return list<string>
     */
    protected function ownMethods(string $name): array
    {
        $methods = [...MediaResourceCheck::LEGACY_METHODS, ...MediaResourceCheck::METHODS];
        if ($this->reflectionProvider->hasClass(self::JSON_RESOURCE)) {
            foreach ($this->reflectionProvider->getClass(self::JSON_RESOURCE)->getNativeReflection()->getMethods() as $method) {
                $methods[] = $method->getName();
            }
        }

        $class = $this->reflectionProvider->getClass($name);
        foreach ([$class, ...$class->getParents()] as $ancestor) {
            if ($ancestor->getName() === MediaResourceCheck::RESOURCE) {
                break;
            }
            foreach ($ancestor->getNativeReflection()->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() === $ancestor->getName()) {
                    $methods[] = $method->getName();
                }
            }
        }

        return array_values(array_unique(array_map('strtolower', $methods)));
    }

    protected function isThis(Node $node): bool
    {
        return $node instanceof Variable && $this->isName($node, 'this');
    }

    protected function isResource(Node $node): bool
    {
        return $node instanceof PropertyFetch && $this->isThis($node->var) && $this->isName($node->name, 'resource');
    }

    protected function entity(): MethodCall
    {
        return new MethodCall(new Variable('this'), 'entity');
    }
}
