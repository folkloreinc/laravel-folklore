<?php

namespace Folklore\Site;

use Folklore\Contracts\Site\Site as SiteContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;

/**
 * A site declared in the `site.sites` config:
 *
 *     'fr' => [
 *         'hosts' => ['example.ca', '*.example.ca'],
 *         'path' => 'fr', // optional
 *         'locale' => 'fr', // any other value, read with get()
 *     ],
 *
 * A request matches the site when its host matches one of the hosts (`*`
 * matches any characters) and, if a path is set, when its path is the path or
 * starts with it. A site without hosts nor path matches no request.
 */
class Site implements Arrayable, JsonSerializable, SiteContract
{
    protected string $id;

    public function __construct(protected array $attributes, ?string $id = null)
    {
        $id = $attributes['id'] ?? $id;
        if (is_null($id) || $id === '') {
            throw new InvalidArgumentException('A site needs an id.');
        }

        $this->id = (string) $id;
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * Host patterns of the site, from `hosts` or `host`.
     */
    public function hosts(): array
    {
        return collect($this->attributes['hosts'] ?? $this->attributes['host'] ?? [])
            ->map(fn ($host) => strtolower(trim($host)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Path prefix of the site, without slashes.
     */
    public function path(): ?string
    {
        $path = trim($this->attributes['path'] ?? '', '/');

        return $path !== '' ? $path : null;
    }

    public function matchRequest(Request $request): bool
    {
        $hosts = $this->hosts();
        $path = $this->path();
        if (count($hosts) === 0 && is_null($path)) {
            return false;
        }

        $host = strtolower($request->getHost());
        if (count($hosts) > 0 && ! collect($hosts)->contains(fn ($pattern) => Str::is($pattern, $host))) {
            return false;
        }

        return is_null($path) || $request->is($path, $path.'/*');
    }

    /**
     * Get a value of the site's config, using "dot" notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->toArray(), $key, $default);
    }

    public function toArray(): array
    {
        return array_merge($this->attributes, ['id' => $this->id]);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
