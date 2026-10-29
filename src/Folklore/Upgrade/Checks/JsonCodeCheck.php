<?php

namespace Folklore\Upgrade\Checks;

use Folklore\Eloquent\JsonDataCast as LegacyJsonDataCast;
use Folklore\JsonData\JsonDataCast;
use Folklore\Upgrade\Finding;
use Folklore\Upgrade\Occurrence;
use Folklore\Upgrade\Site;
use Folklore\Upgrade\SourceFile;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Throwable;

/**
 * Sites that keep their own JSON references: models that replace the `JsonDataCast` of
 * their Folklore base with a plain `json` cast, and code that writes `scheme://id`
 * strings and syncs their pivots itself. In 2.0, they move to declarations
 * (`getJsonDataRelations()`) or schemas (`jsonDataSchema()`), which the cast resolves and
 * syncs; the paths and keys of their data are in the "Stored JSON references" check.
 */
class JsonCodeCheck implements Check
{
    /**
     * The Folklore models whose JSON attributes are cast with `JsonDataCast`.
     *
     * @var array<string, list<string>>
     */
    public const CAST_MODELS = [
        'Folklore\\Models\\Page' => ['data'],
        'Folklore\\Models\\Block' => ['data'],
    ];

    /**
     * Schemes of URLs, which aren't references.
     *
     * @var list<string>
     */
    public const URL_SCHEMES = ['http', 'https', 'ftp', 'ftps', 'sftp', 'ssh', 'git', 'mailto', 'tel', 'sms', 'ws', 'wss', 's3', 'gs', 'file', 'data', 'php', 'phar', 'zip', 'redis', 'mysql', 'pgsql', 'sqlite', 'tcp', 'udp', 'unix'];

    public function title(): string
    {
        return 'JSON references in the code';
    }

    public function check(Site $site): iterable
    {
        foreach ($site->files() as $file) {
            foreach ($this->plainCasts($file) as [$attribute, $cast, $line]) {
                if ($this->overridesJsonDataCast($site, $file, $attribute)) {
                    yield Finding::todo(
                        "`{$file->declaredClass()}` casts `{$attribute}` with a plain `{$cast}` cast, over the `JsonDataCast` of its Folklore model: the references in it are the site's own, which 2.0 doesn't resolve or sync.",
                        'Declare its references (`getJsonDataRelations()`, or `jsonDataSchema()`), cast it with `JsonDataCast`, and remove the code that writes and syncs them. Their paths and keys are in "Stored JSON references".',
                        ["{$file->path}:{$line}"],
                    );
                }
            }
        }

        $writers = $this->referenceWriters($site);
        if (count($writers) === 0) {
            return;
        }
        yield Finding::todo(
            'Code that writes `scheme://id` references itself: 2.0 writes them from declarations or schemas, with keys mapped with `JsonData::map()`.',
            'Move the paths to declarations or schemas of the models, and their keys to `JsonData::map()`; then remove this code.',
            array_map(fn (Occurrence $occurrence) => $occurrence->location().' ('.trim($occurrence->groups[0]).')', $writers),
        );

        $syncs = $this->pivotSyncs($site, $writers);
        if (count($syncs) > 0) {
            yield Finding::todo(
                'Pivot syncs next to that code: `JsonDataCast` syncs the relations of declared references when the model is saved.',
                'Remove the syncs of the relations that the declarations cover.',
                array_map(fn (Occurrence $occurrence) => $occurrence->location(), $syncs),
            );
        }
    }

    /**
     * The attributes of a model's casts that are plain JSON: [attribute, cast, line].
     *
     * @return list<array{string, string, int}>
     */
    protected function plainCasts(SourceFile $file): array
    {
        $code = $file->code();
        if (! str_contains($code, '$casts') && preg_match('/function\s+casts\s*\(/', $code) !== 1) {
            return [];
        }
        $casts = [];
        foreach ($file->lines as $number => $line) {
            if (preg_match('/^\s*[\'"](\w+)[\'"]\s*=>\s*[\'"](json|array|object|collection)[\'"]/', $line, $match) === 1) {
                $casts[] = [$match[1], $match[2], $number];
            }
        }

        return $casts;
    }

    /**
     * Whether the model of a file replaces the `JsonDataCast` of a class it extends: read
     * from the loaded class, or from the file when it doesn't load.
     */
    protected function overridesJsonDataCast(Site $site, SourceFile $file, string $attribute): bool
    {
        $class = $file->declaredClass();
        if (! is_null($class) && Site::loads($class) && is_subclass_of($class, Model::class)) {
            $parent = get_parent_class($class);
            while ($parent !== false) {
                if (! (new ReflectionClass($parent))->isAbstract()) {
                    try {
                        $instance = new $parent;
                        $cast = $instance instanceof Model ? $instance->getCasts()[$attribute] ?? null : null;
                    } catch (Throwable) {
                        $cast = null;
                    }

                    return is_string($cast) && (is_a($cast, JsonDataCast::class, true) || is_a($cast, LegacyJsonDataCast::class, true));
                }
                $parent = get_parent_class($parent);
            }

            return false;
        }

        foreach (self::CAST_MODELS as $model => $attributes) {
            if (in_array($attribute, $attributes, true) && $site->fileReferences($file, $model)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The lines that build a reference: `'media://'.$id`, `$key.'://'.$id`, `"media://{$id}"`.
     *
     * @return list<Occurrence>
     */
    protected function referenceWriters(Site $site): array
    {
        $occurrences = [
            ...$site->matches('/(\.\s*([\'"]):\/\/\2\s*\.)/'),
            ...$site->matches('/(([\'"])([A-Za-z][A-Za-z0-9_-]*):\/\/\2\s*\.\s*\$)/'),
            ...$site->matches('/("([A-Za-z][A-Za-z0-9_-]*):\/\/\{?\$)/'),
        ];
        $writers = [];
        foreach ($occurrences as $occurrence) {
            $scheme = $occurrence->groups[3] ?? $occurrence->groups[2] ?? null;
            if (is_string($scheme) && in_array(strtolower($scheme), self::URL_SCHEMES, true)) {
                continue;
            }
            $writers[$occurrence->location()] = new Occurrence($occurrence->file, $occurrence->line, [$occurrence->groups[1]]);
        }
        ksort($writers, SORT_NATURAL);

        return array_values($writers);
    }

    /**
     * The pivot syncs of the files that write references, and of the classes that use
     * them (a trait of the repositories, a base class).
     *
     * @param  list<Occurrence>  $writers
     * @return list<Occurrence>
     */
    protected function pivotSyncs(Site $site, array $writers): array
    {
        $files = [];
        $names = [];
        foreach ($writers as $writer) {
            $files[$writer->file->path] = true;
            $class = $writer->file->declaredClass();
            if (! is_null($class)) {
                $names[] = $class;
            }
        }
        foreach ($site->files() as $file) {
            foreach (array_unique($names) as $name) {
                $short = preg_quote(class_basename($name), '/');
                if (
                    $site->fileReferences($file, $name)
                    || preg_match('/\b(?:use|extends|implements)\s+[^;{]*\b'.$short.'\b/', $file->code()) === 1
                ) {
                    $files[$file->path] = true;
                }
            }
        }

        return array_values(array_filter(
            $site->matches('/->(?:sync|syncWithoutDetaching|attach)\s*\(/'),
            fn (Occurrence $occurrence) => isset($files[$occurrence->file->path]),
        ));
    }
}
