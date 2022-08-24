<?php

namespace Folklore\Site;

use Folklore\Contracts\Site\Factory;
use Folklore\Contracts\Site\Site;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use InvalidArgumentException;

class Manager implements Factory
{
    protected $container;

    protected $sites;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function site(string $id): Site
    {
        $site = $this->sites()->first(function ($site) use ($id) {
            return $site->id() === $id;
        });
        if (is_null($site)) {
            throw new InvalidArgumentException('Invalid site');
        }
        return $site;
    }

    public function fromRequest(Request $request): ?Site
    {
        return $this->sites()->first(function ($site) use ($request) {
            return $site->matchRequest($request);
        }) ?? $this->site($this->getDefaultSite());
    }

    public function sites(): Collection
    {
        if (!isset($this->sites)) {
            $sites = $this->container['config']->get('site.sites', []);
            $this->sites = collect($sites)->map(function ($site, $id) {
                return $this->makeSite($site, $id);
            });
        }
        return $this->sites;
    }

    protected function makeSite($site, $id = null): Site
    {
        if (is_array($site)) {
            return new Site($site, $id);
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
