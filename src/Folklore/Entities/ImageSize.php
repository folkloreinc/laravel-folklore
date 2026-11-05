<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\Image as ImageContract;
use Folklore\Contracts\Entities\ImageSize as ImageSizeContract;
use Folklore\Image\Exceptions\ImageException;
use Folklore\Image\Urls\V1Codec;
use Folklore\Image\Variants\ImageVariant;

/**
 * An image in one size, with its URL, its dimensions and its type.
 *
 * By default (`site.medias.image_urls` set to `presets`), a size is a laravel-image 2 preset:
 * the preset's 2.x URL, which `Route::image()` serves, and the variant's exact dimensions.
 * With `site.medias.image_urls` set to `legacy`, it is a size of 1.x, as 1.1 gave it: the
 * 1.x URL of the size (`-filters(large)`), which `Route::imageLegacy()` serves, and the
 * dimensions computed from its definition in `image.sizes` (docs/decisions/0024).
 */
class ImageSize implements ImageSizeContract
{
    /**
     * The values of `site.medias.image_urls`.
     */
    public const PRESETS = 'presets';

    public const LEGACY = 'legacy';

    /**
     * The types of the formats of 1.x URLs.
     *
     * @var array<string, string>
     */
    protected const LEGACY_MIMES = [
        'gif' => 'image/gif',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];

    /**
     * @var ImageContract
     */
    protected $image;

    /**
     * @var string
     */
    protected $size;

    protected $filters;

    /**
     * @var string|null
     */
    protected $format;

    protected $url;

    protected $dimension;

    protected ?ImageVariant $variant = null;

    protected bool $resolved = false;

    /**
     * The definition of the size in `image.sizes`, in the legacy mode.
     *
     * @var array<mixed>|null
     */
    protected ?array $legacySize = null;

    protected bool $legacySizeResolved = false;

    /**
     * The dimensions of the size, in the legacy mode.
     *
     * @var array{width: int, height: int}|null
     */
    protected ?array $legacyDimension = null;

    /**
     * @param  string  $size  The name of a laravel-image preset, or, in the legacy mode, the
     *                        id of a size of `image.sizes`.
     * @param  string|null  $format  A format other than the size's (`webp`, `avif`…).
     */
    public function __construct(ImageContract $image, string $size, ?string $format = null, $filters = null)
    {
        $this->image = $image;
        $this->size = $size;
        $this->format = $format;
        $this->filters = $filters;
    }

    /**
     * Whether image sizes give the 1.x URLs and dimensions of 1.1 (`site.medias.image_urls`
     * set to `legacy`), rather than laravel-image 2's presets.
     */
    public static function usesLegacyUrls(): bool
    {
        return config('site.medias.image_urls', self::PRESETS) === self::LEGACY;
    }

    /**
     * The ids of the sizes of `image.sizes`, in their order: a list of sizes with an `id`, or
     * sizes by name, as laravel-image 2 reads them.
     *
     * @return list<string>
     */
    public static function legacySizeIds(): array
    {
        $sizes = config('image.sizes');
        $ids = [];
        foreach (is_array($sizes) ? $sizes : [] as $key => $size) {
            $id = is_string($key) ? $key : (is_array($size) ? ($size['id'] ?? null) : null);
            if (is_string($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function id(): string
    {
        return $this->size;
    }

    /**
     * The URL of the size, or the original's URL when laravel-image can't render it, or an
     * empty string without files.
     */
    public function url(): string
    {
        if (! isset($this->url)) {
            $original = $this->image->urlWithoutFilters();
            if ($original === '') {
                $this->url = '';
            } elseif (self::usesLegacyUrls()) {
                $this->url = $this->legacyUrl($original);
            } else {
                $this->url = $this->variant()->url ?? $original;
            }
        }

        return $this->url;
    }

    public function width(): int
    {
        if (self::usesLegacyUrls() && ! is_null($this->legacySize())) {
            return $this->legacyDimension()['width'];
        }

        return $this->variant()->width ?? $this->image->metadata()->width();
    }

    public function height(): int
    {
        if (self::usesLegacyUrls() && ! is_null($this->legacySize())) {
            return $this->legacyDimension()['height'];
        }

        return $this->variant()->height ?? $this->image->metadata()->height();
    }

    public function mime(): ?string
    {
        if (self::usesLegacyUrls()) {
            $format = $this->legacyFormat();
            if (! is_null($format) && isset(self::LEGACY_MIMES[$format])) {
                return self::LEGACY_MIMES[$format];
            }
            if (! is_null($this->legacySize())) {
                return $this->image->metadata()->mime();
            }
        }

        return $this->variant()?->mime() ?? $this->image->metadata()->mime();
    }

    /**
     * The variant of the preset, or null when laravel-image can't build it: no files, a
     * source without dimensions, or an unknown preset.
     */
    protected function variant(): ?ImageVariant
    {
        if (! $this->resolved) {
            $this->resolved = true;
            if (! $this->image instanceof Image || $this->image->urlWithoutFilters() === '') {
                return null;
            }
            try {
                $image = $this->image->pendingImage($this->filters)?->preset($this->size);
                if (! is_null($image) && ! is_null($this->format)) {
                    $image = $image->format($this->format);
                }
                $this->variant = $image?->variant();
            } catch (ImageException) {
                $this->variant = null;
            }
        }

        return $this->variant;
    }

    /**
     * The 1.x URL of the size, as 1.1 made it: the original's path with the image's filters,
     * the size (but `original`) and the format, on `app.image_url`. An SVG keeps its URL.
     */
    protected function legacyUrl(string $original): string
    {
        $mime = $this->image->metadata()->mime();
        $isSvg = $mime === 'image/svg' || $mime === 'image/svg+xml';
        $filters = match (true) {
            is_array($this->filters) => $this->filters,
            is_string($this->filters) => [$this->filters],
            default => [],
        };
        if ($this->size !== 'original' && ! $isSvg) {
            $filters[] = $this->size;
        }
        $format = $this->legacyFormat();
        if (! is_null($format) && ! $isSvg && preg_match('/\.'.preg_quote($format, '/').'$/', $original) === 0) {
            $filters['format'] = $format;
        }
        if (count($filters) === 0) {
            return $original;
        }

        $host = config('app.image_url', config('app.url'));
        $path = parse_url($original, PHP_URL_PATH);

        return rtrim(is_string($host) ? $host : '', '/').app(V1Codec::class)->make(is_string($path) ? $path : '', $filters);
    }

    /**
     * The format given, or the size's in `image.sizes`.
     */
    protected function legacyFormat(): ?string
    {
        $format = $this->format ?? ($this->legacySize()['format'] ?? null);

        return is_string($format) && $format !== '' ? $format : null;
    }

    /**
     * The definition of the size in `image.sizes`, by its id or its name.
     *
     * @return array<mixed>|null
     */
    protected function legacySize(): ?array
    {
        if (! $this->legacySizeResolved) {
            $this->legacySizeResolved = true;
            $sizes = config('image.sizes');
            foreach (is_array($sizes) ? $sizes : [] as $key => $size) {
                if (is_array($size) && ($key === $this->size || ($size['id'] ?? null) === $this->size)) {
                    $this->legacySize = $size;
                    break;
                }
            }
        }

        return $this->legacySize;
    }

    /**
     * The dimensions of the size, computed as 1.1 did from its definition: `width` and
     * `height` as they are, `maxWidth` and `maxHeight` within the original's dimensions.
     *
     * @return array{width: int, height: int}
     */
    protected function legacyDimension(): array
    {
        if (is_null($this->legacyDimension)) {
            $size = $this->legacySize() ?? [];
            $metadata = $this->image->metadata();
            $imageWidth = $metadata->width();
            $imageHeight = $metadata->height();
            $filterWidth = self::legacyNumber($size['maxWidth'] ?? null);
            $filterHeight = self::legacyNumber($size['maxHeight'] ?? null);

            $dimension = [
                'width' => (int) (self::legacyNumber($size['width'] ?? null) ?? $imageWidth),
                'height' => (int) (self::legacyNumber($size['height'] ?? null) ?? $imageHeight),
            ];

            if ($imageWidth !== 0 && $imageHeight !== 0) {
                if (! is_null($filterWidth) && ! is_null($filterHeight)) {
                    $scale = min(min($filterWidth, $imageWidth) / $imageWidth, min($filterHeight, $imageHeight) / $imageHeight);
                    $dimension['width'] = (int) round($imageWidth * $scale);
                    $dimension['height'] = (int) round($imageHeight * $scale);
                } elseif (! is_null($filterWidth)) {
                    $maxWidth = min($filterWidth, $imageWidth);
                    $scale = $maxWidth / $imageWidth;
                    $dimension['width'] = (int) $maxWidth;
                    $dimension['height'] = (int) round($imageHeight * $scale);
                } elseif (! is_null($filterHeight)) {
                    $maxHeight = min($filterHeight, $imageHeight);
                    $scale = $maxHeight / $imageHeight;
                    $dimension['height'] = (int) $maxHeight;
                    $dimension['width'] = (int) round($imageWidth * $scale);
                }
            }

            $this->legacyDimension = $dimension;
        }

        return $this->legacyDimension;
    }

    /**
     * A number of a size's definition, or null.
     */
    protected static function legacyNumber(mixed $value): int|float|null
    {
        return is_int($value) || is_float($value) ? $value : (is_numeric($value) ? $value + 0 : null);
    }
}
