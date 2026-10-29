<?php

namespace Folklore\Upgrade\Checks;

use Folklore\Upgrade\Finding;
use Folklore\Upgrade\Site;
use Folklore\Upgrade\SourceFile;
use Folklore\Upgrade\Upgrade;
use ReflectionClass;

/**
 * Folklore's `MediaResource`, which gives laravel-mediatheque's canonical shape by default
 * in 2.0, and extends the mediatheque's resource: in a subclass, `$this->resource` is the
 * media model, not the entity, and `toArray()` is typed.
 */
class MediaResourceCheck implements Check
{
    public const RESOURCE = 'Folklore\\Http\\Resources\\MediaResource';

    /**
     * The methods of 1.1's `MediaResource`. In 1.1, a subclass's `$this->method()` that is
     * neither one of these nor one of `JsonResource` went to the entity.
     *
     * @var list<string>
     */
    public const LEGACY_METHODS = [
        'toArray',
        'withoutMetadata',
        'withMetadata',
        'withoutFiles',
        'withFiles',
        'withFilesAsMap',
        'withoutImageSizes',
        'withImageSizes',
    ];

    /**
     * The methods that 2.0's `MediaResource` adds, which a subclass calls on itself.
     *
     * @var list<string>
     */
    public const METHODS = ['entity', 'legacy', 'canonical', 'isLegacy', 'toLegacyArray'];

    public function title(): string
    {
        return 'Media resource';
    }

    public function check(Site $site): iterable
    {
        $uses = [];
        foreach (Upgrade::RESOURCE_CLASSES as $class) {
            foreach ($site->references($class) as $occurrence) {
                $uses[] = $occurrence->location();
            }
        }
        $uses = array_values(array_unique($uses));
        if (count($uses) > 0 && $site->config('site.medias.resource') !== Upgrade::LEGACY_RESOURCE) {
            yield Finding::todo(
                'The site gives media with Folklore\'s `MediaResource`, whose JSON shape is laravel-mediatheque\'s canonical one in 2.0: `id` is the uid, `files` an object by handle.',
                'Set `site.medias.resource` to `legacy` (`folklore:upgrade` does) to keep the shape of 1.1 that the front ends read.',
                $uses,
            );
        } elseif (count($uses) > 0) {
            yield Finding::info('The site gives media with Folklore\'s `MediaResource`, in the shape of 1.1 (`site.medias.resource` is `legacy`).', null, $uses);
        }

        $signatures = [];
        $calls = [];
        $resources = [];
        foreach ($site->files() as $file) {
            if (! self::extendsResource($file)) {
                continue;
            }
            $declared = preg_match_all('/function\s+&?\s*(\w+)\s*\(/', $file->code(), $matches) > 0 ? $matches[1] : [];
            $allowed = array_map('strtolower', [...self::LEGACY_METHODS, ...self::METHODS, ...self::resourceMethods(), ...$declared]);
            foreach ($file->lines as $number => $line) {
                if (preg_match('/function\s+toArray\s*\([^)]*\)(?!\s*:\s*array\b)/', $line) === 1) {
                    $signatures[] = $file->path.':'.$number;
                }
                if (preg_match_all('/\$this\s*->\s*(\w+)\s*\(/', $line, $found) > 0) {
                    foreach ($found[1] as $method) {
                        if (! in_array(strtolower($method), $allowed, true)) {
                            $calls[] = $file->path.':'.$number.' ('.$method.'())';
                        }
                    }
                }
                if (preg_match('/\$this\s*->\s*resource\b(?!\s*\()/', $line) === 1) {
                    $resources[] = $file->path.':'.$number;
                }
            }
        }

        if (count($signatures) > 0) {
            yield Finding::breaking(
                'A subclass of `MediaResource` declares `toArray()` without its types: in 2.0, the parent declares `toArray(Request $request): array`, and PHP refuses the subclass.',
                'Declare `public function toArray(Request $request): array`.',
                $signatures,
            );
        }
        if (count($calls) > 0) {
            yield Finding::breaking(
                'A subclass of `MediaResource` calls the entity through the resource: in 2.0, the resource is the mediatheque\'s media model, so `$this->files()` goes to the model or to the mediatheque\'s resource.',
                'Call the entity: `$this->entity()->files()`.',
                array_values(array_unique($calls)),
            );
        }
        if (count($resources) > 0) {
            yield Finding::breaking(
                'A subclass of `MediaResource` reads `$this->resource`, which was the entity in 1.1 and is the mediatheque\'s media model in 2.0.',
                'Use `$this->entity()` for the entity.',
                $resources,
            );
        }
    }

    /**
     * Whether a file declares a class that extends Folklore's `MediaResource`.
     */
    public static function extendsResource(SourceFile $file): bool
    {
        $code = $file->code();
        if (preg_match('/\bclass\s+\w+\s+extends\s+(\\\\?[\w\\\\]+)/', $code, $match) !== 1) {
            return false;
        }

        $parent = $match[1];
        if (str_starts_with($parent, '\\')) {
            return ltrim($parent, '\\') === self::RESOURCE;
        }
        [$first] = explode('\\', $parent);
        $rest = substr($parent, strlen($first));
        if (preg_match_all('/^\s*use\s+\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $code, $uses, PREG_SET_ORDER) > 0) {
            foreach ($uses as $use) {
                $alias = $use[2] ?? '';
                $alias = $alias !== '' ? $alias : substr((string) strrchr('\\'.$use[1], '\\'), 1);
                if (strcasecmp($alias, $first) === 0) {
                    return $use[1].$rest === self::RESOURCE;
                }
            }
        }
        $namespace = preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $code, $found) === 1 ? $found[1].'\\' : '';

        return $namespace.$parent === self::RESOURCE;
    }

    /**
     * The methods of Laravel's `JsonResource`, which a subclass calls on itself.
     *
     * @return list<string>
     */
    public static function resourceMethods(): array
    {
        $class = 'Illuminate\\Http\\Resources\\Json\\JsonResource';
        if (! class_exists($class)) {
            return [];
        }

        return array_map(fn (\ReflectionMethod $method) => $method->getName(), (new ReflectionClass($class))->getMethods());
    }
}
