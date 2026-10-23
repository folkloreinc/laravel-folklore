<?php

namespace Folklore\Eloquent;

use Closure;
use Folklore\Contracts\Entities\HasModel;
use Folklore\Mediatheque\Contracts\Models\Media;
use Folklore\Mediatheque\Facades\Mediatheque;
use Folklore\Mediatheque\References\MediaReference;
use Folklore\Mediatheque\Support\Uid;
use Folklore\Repositories\Medias;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;

/**
 * The reference handler of the media, in the medias module, for the `media` (by uid) and
 * `medias` (by id, 1.x) keys (docs/json-data.md, "Media references", decisions/0008).
 * It takes what forms and seeders give, ingests files and URLs with the mediatheque, and
 * resolves references with `Mediatheque::resolveMany()`.
 */
class MediaReferences extends ModelReferences
{
    /**
     * @var (Closure(Model): array<string, mixed>)|null
     */
    protected static ?Closure $ingestUsing = null;

    /**
     * @var (Closure(Media, Model|null): bool)|null
     */
    protected static ?Closure $allowUsing = null;

    /**
     * The attributes of the media ingested for an owner, such as its tenant:
     * `MediaReferences::ingestUsing(fn (Model $owner) => ['workspace_id' => $owner->workspace_id])`.
     *
     * @param  (Closure(Model): array<string, mixed>)|null  $callback
     */
    public static function ingestUsing(?Closure $callback): void
    {
        static::$ingestUsing = $callback;
    }

    /**
     * Whether an owner may refer to a media, such as one of its tenant:
     * `MediaReferences::allowUsing(fn (Media $media, Model $owner) => $media->workspace_id === $owner->workspace_id)`.
     *
     * @param  (Closure(Media, Model|null): bool)|null  $callback
     */
    public static function allowUsing(?Closure $callback): void
    {
        static::$allowUsing = $callback;
    }

    /**
     * A media, a media entity, a reference of either key (`media://med_…`, `medias://12`),
     * a uid, a 1.x id, a Panneau 5 value (`['id' => 'med_…']`), an uploaded file, a file,
     * or a URL, which is downloaded with the medias repository's limits, then ingested.
     */
    public function prepare(mixed $value, ReferenceContext $context): ?Model
    {
        if ($value instanceof Media && $value instanceof Model) {
            return $context->target->accepts($value) ? $value : null;
        }

        if ($value instanceof HasModel) {
            return $this->prepare($value->getModel(), $context);
        }

        if ($value instanceof UploadedFile) {
            return $this->ingest($value, $context);
        }

        if ($value instanceof File) {
            $path = $value->getRealPath();

            return $path === false ? null : $this->ingest($path, $context);
        }

        if (is_array($value) && isset($value['id']) && (is_string($value['id']) || is_int($value['id']))) {
            return $this->prepare($value['id'], $context);
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $value) === 1) {
            return $this->ingestUrl($value, $context);
        }

        if (is_string($value) && ! is_null($reference = MediaReference::tryParse($value))) {
            return $this->resolveReference($reference, $context);
        }

        if (is_string($value) && Uid::isValid($value)) {
            return $this->resolveReference(MediaReference::uid($value), $context);
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $id = (int) $value;

            return $id > 0 ? $this->resolveReference(MediaReference::legacy($id), $context) : null;
        }

        return null;
    }

    /**
     * Resolves the identifiers of the context's key in one call to
     * `Mediatheque::resolveMany()`.
     */
    public function resolveMany(array $identifiers, ReferenceContext $context): array
    {
        $references = [];
        foreach (array_unique($identifiers) as $identifier) {
            $reference = $this->referenceOf($identifier, $context);
            if (! is_null($reference)) {
                $references[(string) $reference] = $identifier;
            }
        }
        if (count($references) === 0) {
            return [];
        }

        $models = [];
        foreach (Mediatheque::resolveMany(array_keys($references)) as $reference => $media) {
            if ($media instanceof Model && isset($references[$reference]) && $context->target->accepts($media)) {
                $models[$references[$reference]] = $media;
            }
        }

        return $models;
    }

    public function allows(Model $resolved, ReferenceContext $context): bool
    {
        if (is_null(static::$allowUsing)) {
            return true;
        }

        return $resolved instanceof Media && (static::$allowUsing)($resolved, $context->owner);
    }

    /**
     * The reference of an identifier of the context's key: by uid, or by id for `medias`.
     */
    protected function referenceOf(string $identifier, ReferenceContext $context): ?MediaReference
    {
        if ($context->target->column() === 'uid') {
            return Uid::isValid($identifier) ? MediaReference::uid($identifier) : null;
        }

        return ctype_digit($identifier) && (int) $identifier > 0 ? MediaReference::legacy((int) $identifier) : null;
    }

    protected function resolveReference(MediaReference $reference, ReferenceContext $context): ?Model
    {
        $media = Mediatheque::resolve($reference);

        return $media instanceof Model && $context->target->accepts($media) ? $media : null;
    }

    /**
     * @param  UploadedFile|string  $source  An uploaded file or a local path.
     */
    protected function ingest(UploadedFile|string $source, ReferenceContext $context): ?Model
    {
        $attributes = ! is_null(static::$ingestUsing) && ! is_null($context->owner)
            ? (static::$ingestUsing)($context->owner)
            : [];
        $media = Mediatheque::ingest($source, attributes: $attributes);

        return $media instanceof Model ? $media : null;
    }

    /**
     * Downloads a URL with the medias repository's limits (`site.medias.download`), then
     * ingests it. The mediatheque ingests URLs itself once it has its SSRF guard.
     */
    protected function ingestUrl(string $url, ReferenceContext $context): ?Model
    {
        $medias = Container::getInstance()->make(Medias::class);
        $path = $medias->downloadForIngest($url);
        if (is_null($path)) {
            return null;
        }

        try {
            return $this->ingest($path, $context);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
