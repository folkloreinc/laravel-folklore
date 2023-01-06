<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\HasModel;
use Illuminate\Support\Collection;
use Folklore\Contracts\Resources\Page as PageContract;
use Folklore\Contracts\Resources\PageMetadata as PageMetadataContract;
use Folklore\Contracts\Resources\Image as ImageContract;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Models\Page as PageModel;
use Illuminate\Database\Eloquent\Model;

class Page implements PageContract, HasModel
{
    protected $model;

    protected $data;

    protected $image;

    protected $metadata;

    protected $blocks;

    protected $parent;

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
        if (!isset($this->image)) {
            $model = data_get($this->data, 'image');
            $this->image = $model instanceof Resourcable ? $model->toResource() : $model;
        }

        return $this->image;
    }

    public function metadata(): PageMetadataContract
    {
        if (!isset($this->metadata)) {
            $this->metadata = new PageMetadata($this, $this->model);
        }
        return $this->metadata;
    }

    public function parent(): ?PageContract
    {
        if (!isset($this->parent) && !empty($this->model->parent)) {
            $model = $this->model->parent;
            $this->parent = $model instanceof Resourcable ? $model->toResource() : $model;
        }
        return $this->parent;
    }

    public function children(): Collection
    {
        if (!isset($this->children)) {
            $this->children = $this->model->children->map(function ($model) {
                return $model instanceof Resourcable ? $model->toResource() : $model;
            });
        }
        return $this->children;
    }

    public function blocks(): Collection
    {
        if (!isset($this->blocks)) {
            $this->blocks = collect(data_get($this->data, 'blocks', []))->map(function ($block) {
                return $block instanceof Resourcable ? $block->toResource() : $block;
            });
        }
        return $this->blocks;
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
