<?php

namespace Folklore\Site;

use Folklore\Contracts\Site\Factory;
use Folklore\Contracts\Site\Site as SiteContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Sites declared in the `site.sites` config, keyed by id. Each entry is an
 * array of config (see Site), the class name of a site, or a site instance.
 * `site.default` is the id of the site used when no site matches a request.
 */
class Manager implements Factory
{
    protected $container;

    protected $sites;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function site(string $id): SiteContract
    {
        $site = $this->sites()->first(function ($site) use ($id) {
            return $site->id() === $id;
        });
        if (is_null($site)) {
            throw new InvalidArgumentException('Site ['.$id.'] is not defined.');
        }

        return $site;
    }

    /**
     * The first site matching the request, in the config order, or the
     * default site.
     */
    public function fromRequest(Request $request): ?SiteContract
    {
        $site = $this->sites()->first(function ($site) use ($request) {
            return $site->matchRequest($request);
        });
        if (! is_null($site)) {
            return $site;
        }

        $default = $this->getDefaultSite();

        return ! is_null($default) ? $this->site($default) : null;
    }

    public function sites(): Collection
    {
        if (! isset($this->sites)) {
            $sites = $this->container['config']->get('site.sites', []);
            $this->sites = collect($sites)->map(function ($site, $id) {
                return $this->makeSite($site, $id);
            });
        }

        return $this->sites;
    }

    protected function makeSite($site, $id = null): SiteContract
    {
        if (is_array($site)) {
            return new Site($site, is_string($id) ? $id : null);
        }
        if (is_string($site)) {
            return $this->container->make($site);
        }

        return $site;
    }

    protected function getDefaultSite(): ?string
    {
        return $this->container['config']->get('site.default', null);
    }
}
