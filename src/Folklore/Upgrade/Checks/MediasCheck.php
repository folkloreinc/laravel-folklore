<?php

namespace Folklore\Upgrade\Checks;

use Folklore\Upgrade\Finding;
use Folklore\Upgrade\Occurrence;
use Folklore\Upgrade\Site;

/**
 * What changes with `laravel-mediatheque` 2 and `laravel-image` 2: the `HasMedias` trait,
 * image sizes as presets, and the 1.x API of the media entities.
 */
class MediasCheck implements Check
{
    public function title(): string
    {
        return 'Medias';
    }

    public function check(Site $site): iterable
    {
        $hasMedias = $this->locations($site->references('Folklore\\Mediatheque\\Support\\Traits\\HasMedias'));
        if (count($hasMedias) > 0) {
            yield Finding::breaking(
                '`Folklore\\Mediatheque\\Support\\Traits\\HasMedias` is gone in `laravel-mediatheque` 2.',
                'Use `Folklore\\Mediatheque\\Concerns\\HasMedias`.',
                $hasMedias,
            );
        }

        $sizes = $this->locations([
            ...$site->matches('/[\'"]image\.sizes[\'"]/'),
            ...$site->matches('/^\s*[\'"]sizes[\'"]\s*=>/', ['config/image.php']),
        ]);
        if (count($sizes) > 0) {
            yield Finding::todo(
                'Image sizes as `laravel-image` 1.x filters (`image.sizes`): 2.0 gives one size per `laravel-image` 2 preset.',
                'Move each size to a preset of `config(\'image.presets\')`, with `width`, `height`, `fit`, `format` or `quality`.',
                $sizes,
            );
        }

        $imageSizes = [];
        foreach ($site->matches('/new\s+\\\\?(?:[\w\\\\]*\\\\)?ImageSize\s*\(\s*[^,()]+,\s*+(?![\'"])([^,)]+)/') as $occurrence) {
            if ($site->fileReferences($occurrence->file, 'Folklore\\Entities\\ImageSize') || $occurrence->file->declaredClass() === 'Folklore\\Entities\\ImageSize') {
                $imageSizes[] = $occurrence->location();
            }
        }
        if (count($imageSizes) > 0) {
            yield Finding::breaking(
                '`new ImageSize($image, $filters)`: in 2.0, its second argument is the name of a `laravel-image` preset.',
                'Pass a preset name: `new ImageSize($image, \'card\', $format)`.',
                $imageSizes,
            );
        }

        $setFilters = $this->locations($site->matches('/->setFilters\s*\(/'));
        if (count($setFilters) > 0) {
            yield Finding::deprecated(
                '`Image::setFilters()` only understands a preset name, or `width`, `height`, `fit`, `format` and `quality`; the other 1.x filters are ignored.',
                'Use the image\'s sizes, or `Image::for($media)->preset(...)` of `laravel-image`.',
                $setFilters,
            );
        }

        $metadatas = $this->locations($site->matches('/->getMetadatas\s*\(/'));
        if (count($metadatas) > 0) {
            yield Finding::breaking(
                '`getMetadatas()` is removed: the media models of `laravel-mediatheque` 2 keep their metadata in a column.',
                'Read `$media->metadata(\'key\')`, or the metadata entities.',
                $metadatas,
            );
        }

        yield from $this->checkMediaResource($site);

        $medias = [];
        foreach ($site->matches('/new\s+\\\\?(?:[\w\\\\]*\\\\)?Medias\s*\(\s*\$/') as $occurrence) {
            if ($site->fileReferences($occurrence->file, 'Folklore\\Repositories\\Medias')) {
                $medias[] = $occurrence->location();
            }
        }
        if (count($medias) > 0) {
            yield Finding::breaking(
                '`new Medias($typeFactory)`: the medias repository takes no argument in 2.0.',
                'Resolve it from the container, or call `new Medias()`.',
                $medias,
            );
        }
    }

    /**
     * `MediaResource` extends the mediatheque's resource in 2.0: its shape is set by
     * `site.medias.resource`, and its subclasses get the model as `$this->resource`.
     *
     * @return iterable<Finding>
     */
    protected function checkMediaResource(Site $site): iterable
    {
        $resource = 'Folklore\\Http\\Resources\\MediaResource';
        $uses = $this->locations([
            ...$site->references($resource),
            ...$site->references('Folklore\\Http\\Resources\\MediasCollection'),
        ]);
        if (count($uses) > 0 && $site->config('site.medias.resource', 'legacy') === 'canonical') {
            yield Finding::todo(
                '`site.medias.resource` is `canonical`, so `MediaResource` gives the mediatheque\'s shape, not the one of 1.1 that front ends written for it read: `id` is the uid, `files` an object by handle.',
                'Set `site.medias.resource` to `legacy` until the front ends read the canonical shape.',
                $uses,
            );
        }

        $untyped = [];
        $throughResource = [];
        foreach ($site->files() as $file) {
            if (! $site->fileReferences($file, $resource)) {
                continue;
            }
            $code = $file->code();
            $alias = preg_match('/^\s*use\s+\\\\?Folklore\\\\Http\\\\Resources\\\\MediaResource\s+as\s+(\w+)\s*;/m', $code, $match) === 1 ? $match[1] : 'MediaResource';
            if (preg_match('/\bextends\s+(?:\\\\?Folklore\\\\Http\\\\Resources\\\\)?'.preg_quote($alias, '/').'\b/', $code) !== 1) {
                continue;
            }
            foreach ($file->lines as $number => $line) {
                if (preg_match('/function\s+toArray\s*\(\s*\$\w+\s*\)(?!\s*:)/', $line) === 1) {
                    $untyped[] = "{$file->path}:{$number}";
                }
                if (preg_match('/\$this->(?:files|metadata|thumbnailUrl|sizes)\s*\(/', $line) === 1) {
                    $throughResource[] = "{$file->path}:{$number}";
                }
            }
        }
        if (count($untyped) > 0) {
            yield Finding::breaking(
                'A subclass of `MediaResource` overrides `toArray($request)` without types: the mediatheque\'s `toArray(Request $request): array` makes it a fatal error in 2.0.',
                'Declare it `toArray(Request $request): array`.',
                $untyped,
            );
        }
        if (count($throughResource) > 0) {
            yield Finding::breaking(
                'A subclass of `MediaResource` calls the entity through the resource: in 2.0, `$this->resource` is the mediatheque\'s model, and `files()` and `thumbnailUrl()` are the parent\'s.',
                'Call the entity: `$this->entity()->files()`.',
                $throughResource,
            );
        }
    }

    /**
     * @param  list<Occurrence>  $occurrences
     * @return list<string>
     */
    protected function locations(array $occurrences): array
    {
        return array_map(fn (Occurrence $occurrence) => $occurrence->location(), $occurrences);
    }
}
