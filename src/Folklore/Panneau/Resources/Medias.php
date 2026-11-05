<?php

namespace Folklore\Panneau\Resources;

use Folklore\Panneau\Resources\Concerns\EditsFields;
use Panneau\Fields\File;
use Panneau\Fields\Text;
use Panneau\Lists\ResourceList;
use Panneau\Mediatheque\MediathequeResource;

/**
 * The media library of the site, on `laravel-mediatheque` 2 (docs/decisions/0025): images,
 * videos and documents by default (`mediaTypes()`), in a gallery and a table with their
 * thumbnails. A site extends it, even empty, and registers its class:
 *
 * ```php
 * class Medias extends \Folklore\Panneau\Resources\Medias {}
 * ```
 *
 * The file takes the largest size of the library's types (`mediatheque.types.*.max_size`),
 * and the mediatheque checks each type's own on upload. Deleting a media that pages use skips
 * it (`laravel-panneau` 5.0.0-alpha.14).
 */
class Medias extends MediathequeResource
{
    use EditsFields;

    public function singularLabel(): string
    {
        return static::trans('resources.medias.singular');
    }

    public function pluralLabel(): string
    {
        return static::trans('resources.medias.plural');
    }

    public function icon(): ?string
    {
        return 'library';
    }

    /**
     * The types of the mediatheque the library takes.
     *
     * @return list<string>
     */
    public function mediaTypes(): array
    {
        return ['image', 'video', 'document'];
    }

    public function fields(): array
    {
        return [
            Text::make('name')->label(static::trans('fields.name')),
        ];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->search(['name'])->layouts(['gallery', 'table']);
    }

    protected function file(File $file): File
    {
        return $file->label(static::trans('fields.file'));
    }
}
