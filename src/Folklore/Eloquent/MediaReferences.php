<?php

namespace Folklore\Eloquent;

use Closure;
use Folklore\Contracts\Entities\HasModel;
use Folklore\Eloquent\Exceptions\InvalidJsonReference;
use Folklore\Mediatheque\Contracts\Models\Media;
use Folklore\Mediatheque\Facades\Mediatheque;
use Folklore\Mediatheque\References\MediaReference;
use Folklore\Mediatheque\Support\Uid;
use Folklore\Repositories\Medias;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * The reference handler of the media, in the medias module, for the `media` (by uid) and
 * `medias` (by id, 1.x) keys (docs/json-data.md, "Media references", decisions/0008).
 * It takes what forms and seeders give, ingests files and URLs with the mediatheque, and
 * resolves references through the media model's query, as `Mediatheque::resolveMany()`
 * does, so that they resolve under `Mediatheque::fake()` too.
 */
class MediaReferences extends ModelReferences
{
    /**
     * The forms of values that `prepare()` can take: a media model or entity, a `media://`
     * reference, a uid, a 1.x id (`12` or `medias://12`), a file, or a URL.
     */
    public const array FORMS = ['model', 'reference', 'uid', 'legacy_id', 'file', 'url'];

    /**
     * The forms that can only name an existing media by its public id: no internal id, no
     * file to ingest, no URL to download.
     */
    public const array STRICT = ['model', 'reference', 'uid'];

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
     * Only the forms of `site.medias.references.accept` are taken (acceptedForms()).
     */
    public function prepare(mixed $value, ReferenceContext $context): ?Model
    {
        if ($value instanceof HasModel) {
            return $this->prepare($value->getModel(), $context);
        }

        if (is_array($value) && isset($value['id']) && (is_string($value['id']) || is_int($value['id']))) {
            return $this->prepare($value['id'], $context);
        }

        $form = $this->formOf($value);
        if (is_null($form)) {
            return null;
        }
        if (! in_array($form, $this->acceptedForms(), true)) {
            return $this->refuse($form, $context);
        }

        if ($value instanceof Media && $value instanceof Model) {
            return $context->target->accepts($value) ? $value : null;
        }

        if ($value instanceof UploadedFile) {
            return $this->ingest($value, $context);
        }

        if ($value instanceof File) {
            $path = $value->getRealPath();

            return $path === false ? null : $this->ingest($path, $context);
        }

        if ($form === 'url' && is_string($value)) {
            return $this->ingestUrl($value, $context);
        }

        if (is_string($value) && ! is_null($reference = MediaReference::tryParse($value))) {
            return $this->resolveReference($reference, $context);
        }

        if (is_string($value) && Uid::isValid($value)) {
            return $this->resolveReference(MediaReference::uid($value), $context);
        }

        $id = is_int($value) || is_string($value) ? (int) $value : 0;

        return $id > 0 ? $this->resolveReference(MediaReference::legacy($id), $context) : null;
    }

    /**
     * The forms that `prepare()` takes: `site.medias.references.accept`, or by default
     * the strict ones under `JsonData::enforceMap()`, and every form otherwise, as in 1.x.
     *
     * @return list<string>
     */
    public function acceptedForms(): array
    {
        $accept = Container::getInstance()->make('config')->get('site.medias.references.accept');
        if (is_null($accept)) {
            return JsonData::enforcesMap() ? self::STRICT : self::FORMS;
        }
        if (! is_array($accept)) {
            throw new InvalidArgumentException('site.medias.references.accept must be a list of forms, or null.');
        }

        $forms = [];
        foreach ($accept as $form) {
            if (! is_string($form) || ! in_array($form, self::FORMS, true)) {
                throw new InvalidArgumentException('site.medias.references.accept has an unknown form ['.(is_scalar($form) ? $form : get_debug_type($form)).']. The forms are: '.implode(', ', self::FORMS).'.');
            }
            $forms[] = $form;
        }

        return $forms;
    }

    /**
     * The form of a value that `prepare()` takes, or null when it isn't one.
     */
    protected function formOf(mixed $value): ?string
    {
        if ($value instanceof Media && $value instanceof Model) {
            return 'model';
        }
        if ($value instanceof UploadedFile || $value instanceof File) {
            return 'file';
        }
        if (is_int($value)) {
            return 'legacy_id';
        }
        if (! is_string($value)) {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $value) === 1) {
            return 'url';
        }
        if (! is_null($reference = MediaReference::tryParse($value))) {
            return $reference->isLegacy() ? 'legacy_id' : 'reference';
        }
        if (Uid::isValid($value)) {
            return 'uid';
        }

        return ctype_digit($value) ? 'legacy_id' : null;
    }

    /**
     * A value of a form that isn't accepted: an error under `JsonData::enforceMap()`, and
     * written `null` otherwise, as a value the handler doesn't take.
     */
    protected function refuse(string $form, ReferenceContext $context): ?Model
    {
        if (JsonData::enforcesMap()) {
            $path = $context->attribute.(is_null($context->path) ? '' : '.'.$context->path);

            throw new InvalidJsonReference("The value at [{$path}] on [{$context->ownerClass}] is a media [{$form}], which site.medias.references.accept doesn't accept.");
        }

        return null;
    }

    /**
     * Resolves the identifiers of the context's key with one query on its column, as
     * `Mediatheque::resolveMany()` does. Not through the facade: `Mediatheque::fake()`
     * only knows the media it ingested, in memory, which aren't models.
     */
    public function resolveMany(array $identifiers, ReferenceContext $context): array
    {
        return parent::resolveMany(array_values(array_filter(
            $identifiers,
            fn (string $identifier) => ! is_null($this->referenceOf($identifier, $context)),
        )), $context);
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
        $query = $this->query($context->target);
        $media = $reference->isLegacy()
            ? $query->whereKey($reference->id)->first()
            : $query->where('uid', $reference->uid)->first();

        return $media instanceof Model && $context->target->accepts($media) ? $media : null;
    }

    /**
     * The media model's query, with its files, as the mediatheque resolves media.
     *
     * @return Builder<Model>
     */
    protected function query(JsonReference $target): Builder
    {
        return parent::query($target)->with('mediaFiles');
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
