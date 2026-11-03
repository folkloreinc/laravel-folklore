<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\Image as ImageContract;
use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Entities\PageMetadata as PageMetadataContract;
use Folklore\Models\Page as PageModel;
use Folklore\Pages\BlockRegistry;
use Folklore\Pages\PageRoutes;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Page implements HasModel, PageContract
{
    protected $model;

    protected $data;

    public function __construct(PageModel $model)
    {
        $this->model = $model;
        $this->data = $model->data;
    }

    public function id(): string
    {
        return $this->model->id;
    }

    public function handle(): ?string
    {
        return $this->model->handle;
    }

    public function type(): string
    {
        return $this->model->type ?? 'page';
    }

    public function pageType(): string
    {
        return $this->model->type ?? 'page';
    }

    public function published(): bool
    {
        return $this->model->published ?? false;
    }

    public function slug(string $locale): ?string
    {
        return $this->model->{'slug_'.$locale};
    }

    public function title(string $locale): string
    {
        return data_get($this->data, 'title.'.$locale) ?? '';
    }

    public function description(string $locale): ?string
    {
        return data_get($this->data, 'description.'.$locale);
    }

    /**
     * The URL of the page, from the routes of `site.pages.routes` (Pages\PageRoutes), with or
     * without the locale in their names. A page with a parent is at `page` when the site has
     * no `page_with_parent` route.
     */
    public function url(string $locale, bool $absolute = false): string
    {
        if ($this->handle() === 'home') {
            return $this->routeUrl('home', $locale, [], $absolute);
        }

        $parent = $this->parent();
        if (! is_null($parent) && PageRoutes::routeName('page_with_parent', $locale) !== null) {
            return $this->routeUrl(
                'page_with_parent',
                $locale,
                [
                    'parent' => $parent->slug($locale),
                    'page' => $this->slug($locale),
                ],
                $absolute
            );
        }

        return $this->routeUrl(
            'page',
            $locale,
            [
                'page' => $this->slug($locale),
            ],
            $absolute
        );
    }

    /**
     * The URL of a route of pages. When the site has no such route, the route with the locale
     * is asked for, and is not found.
     *
     * @param  array<string, string|null>  $parameters
     */
    private function routeUrl(string $route, string $locale, array $parameters, bool $absolute): string
    {
        $name = PageRoutes::routeName($route, $locale) ?? $locale.'.'.PageRoutes::name($route);

        return app('url')->route($name, $parameters, $absolute);
    }

    public function image(): ?ImageContract
    {
        return once(fn () => to_entity(data_get($this->data, 'image')));
    }

    public function metadata(): PageMetadataContract
    {
        return once(fn () => new PageMetadata($this, $this->model));
    }

    public function parent(): ?PageContract
    {
        return once(fn () => to_entity($this->model->parent));
    }

    public function children(): Collection
    {
        return once(
            fn () => $this->model->children->toBase()->map(function ($model) {
                return to_entity($model);
            })
        );
    }

    /**
     * The page's blocks, from its data, as entities of their types (`site.pages.blocks`).
     */
    public function blocks(): Collection
    {
        return once(fn () => Container::getInstance()->make(BlockRegistry::class)->toEntities(data_get($this->data, 'blocks', [])));
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
