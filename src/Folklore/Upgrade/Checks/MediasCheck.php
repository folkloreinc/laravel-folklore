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
     * @param  list<Occurrence>  $occurrences
     * @return list<string>
     */
    protected function locations(array $occurrences): array
    {
        return array_map(fn (Occurrence $occurrence) => $occurrence->location(), $occurrences);
    }
}
