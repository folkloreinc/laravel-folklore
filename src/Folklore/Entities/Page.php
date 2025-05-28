<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\HasModel;
use Illuminate\Support\Collection;
use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Entities\PageMetadata as PageMetadataContract;
use Folklore\Contracts\Entities\Image as ImageContract;
use Folklore\Models\Page as PageModel;
use Illuminate\Database\Eloquent\Model;

class Page implements PageContract, HasModel
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
        return $this->model->{'slug_' . $locale};
    }

    public function title(string $locale): string
    {
        return data_get($this->data, 'title.' . $locale) ?? '';
    }

    public function description(string $locale): ?string
    {
        return data_get($this->data, 'description.' . $locale);
    }

    public function url(string $locale, bool $absolute = false): string
    {
        if ($this->handle() === 'home') {
            return route_with_locale('home', $locale, [], $absolute);
        }

        $parent = $this->parent();
        if (!is_null($parent)) {
            return route_with_locale(
                'page_with_parent',
                $locale,
                [
                    'parent' => $parent->slug($locale),
                    'page' => $this->slug($locale),
                ],
                $absolute
            );
        }

        return route_with_locale(
            'page',
            $locale,
            [
                'page' => $this->slug($locale),
            ],
            $absolute
        );
    }

    public function image(): ?ImageContract
    {
        return once(fn() => to_entity(data_get($this->data, 'image')));
    }

    public function metadata(): PageMetadataContract
    {
        return once(fn() => new PageMetadata($this, $this->model));
    }

    public function parent(): ?PageContract
    {
        return once(fn() => to_entity($this->model->parent));
    }

    public function children(): Collection
    {
        return once(
            fn() => $this->model->children->toBase()->map(function ($model) {
                return to_entity($model);
            })
        );
    }

    public function blocks(): Collection
    {
        return once(
            fn() => collect(data_get($this->data, 'blocks', []))->map(function ($block) {
                return to_entity($block);
            })
        );
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
