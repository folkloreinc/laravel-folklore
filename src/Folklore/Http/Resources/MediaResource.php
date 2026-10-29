<?php

namespace Folklore\Http\Resources;

use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\Image;
use Folklore\Contracts\Entities\Media as MediaEntity;
use Folklore\Contracts\Entities\MediaFile;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Mediatheque\Contracts\Models\Media;
use Folklore\Mediatheque\Http\Resources\MediaResource as BaseMediaResource;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;

/**
 * A media in JSON, from its entity or its model.
 *
 * By default, the canonical shape of laravel-mediatheque's `MediaResource`: `id` is the
 * uid, `files` an object by handle. With `legacy()`, or `site.medias.resource` set to
 * `legacy`, the shape that 1.1 gave and that front ends written for it read: `id` is the
 * internal id, `url` the original's, `metadata` the entity's, `sizes` an image's
 * laravel-image presets, and `files` a list with 1.x handles (`thumbnails.0`).
 *
 * `$this->resource` is the mediatheque's model; `entity()` gives the media's entity.
 */
class MediaResource extends BaseMediaResource
{
    public const CANONICAL = 'canonical';

    public const LEGACY = 'legacy';

    protected $withFiles = true;

    protected $withMetadata = true;

    protected $withImageSizes = true;

    protected $withFilesAsMap = false;

    /**
     * The shape, or null for `site.medias.resource`.
     */
    protected ?string $shape = null;

    protected ?MediaEntity $entity = null;

    /**
     * @param  MediaEntity|Media  $media  An entity of the medias module, or a media model.
     */
    public function __construct(MediaEntity|Media $media)
    {
        $model = $media instanceof HasModel ? $media->getModel() : $media;
        if (! $model instanceof Media) {
            throw new InvalidArgumentException(sprintf(
                'MediaResource needs a media of laravel-mediatheque, or an entity whose model is one; got %s.',
                get_debug_type($model),
            ));
        }

        parent::__construct($model);

        $this->entity = $media instanceof MediaEntity ? $media : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->isLegacy()) {
            return $this->toLegacyArray($request);
        }

        $data = parent::toArray($request);
        if (! $this->withMetadata) {
            unset($data['metadata']);
        }
        if (! $this->withFiles) {
            unset($data['files']);
        }

        return $data;
    }

    /**
     * The shape of 1.1, field for field.
     *
     * @return array<string, mixed>
     */
    protected function toLegacyArray(Request $request): array
    {
        $media = $this->entity();

        return [
            'id' => $media->id(),
            'type' => method_exists($media, 'type') ? $media->type() : $this->resource->type(),
            'name' => method_exists($media, 'name') ? $media->name() : ($this->resource->name() ?? ''),
            'url' => $media->url(),
            'thumbnail_url' => $media->thumbnailUrl(),
            'metadata' => $this->when($this->withMetadata, fn () => new MediaMetadataResource($media->metadata())),
            'sizes' => $this->when(
                $this->withImageSizes && $media instanceof Image,
                fn () => $media instanceof Image ? ImageSizeResource::collection($media->sizes()) : null,
            ),
            'files' => $this->when($this->withFiles, function () use ($media) {
                if ($this->withFilesAsMap) {
                    return $media->files()
                        ->filter(fn (mixed $file) => $file instanceof MediaFile)
                        ->mapWithKeys(fn (MediaFile $file) => [(string) $file->handle() => new MediaFileResource($file)]);
                }

                return MediaFileResource::collection($media->files());
            }),
        ];
    }

    /**
     * The entity of the media: the one the resource was made with, or its model's.
     */
    public function entity(): MediaEntity
    {
        if (is_null($this->entity)) {
            $entity = $this->resource instanceof ToEntity ? $this->resource->toEntity() : null;
            if (! $entity instanceof MediaEntity) {
                throw new LogicException(sprintf(
                    'The legacy shape of MediaResource reads the entity of the media, and %s has none: make it implement %s.',
                    get_class($this->resource),
                    ToEntity::class,
                ));
            }
            $this->entity = $entity;
        }

        return $this->entity;
    }

    /**
     * The shape of 1.1, whatever `site.medias.resource` says.
     */
    public function legacy(bool $legacy = true): static
    {
        $this->shape = $legacy ? self::LEGACY : self::CANONICAL;

        return $this;
    }

    /**
     * The canonical shape of laravel-mediatheque, whatever `site.medias.resource` says.
     */
    public function canonical(): static
    {
        return $this->legacy(false);
    }

    public function isLegacy(): bool
    {
        return ($this->shape ?? config('site.medias.resource', self::CANONICAL)) === self::LEGACY;
    }

    public function withoutMetadata()
    {
        $this->withMetadata = false;

        return $this;
    }

    public function withMetadata()
    {
        $this->withMetadata = true;

        return $this;
    }

    public function withoutFiles()
    {
        $this->withFiles = false;

        return $this;
    }

    public function withFiles()
    {
        $this->withFiles = true;

        return $this;
    }

    /**
     * In the legacy shape, the files by handle rather than in a list. The canonical shape
     * always has them by handle.
     */
    public function withFilesAsMap()
    {
        $this->withFilesAsMap = true;

        return $this;
    }

    /**
     * Without the `sizes` of an image, in the legacy shape. The canonical shape has none.
     */
    public function withoutImageSizes()
    {
        $this->withImageSizes = false;

        return $this;
    }

    public function withImageSizes()
    {
        $this->withImageSizes = true;

        return $this;
    }
}
