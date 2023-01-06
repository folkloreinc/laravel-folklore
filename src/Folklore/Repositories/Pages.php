<?php

namespace Folklore\Repositories;

use Folklore\Models\Page as PageModel;
use Folklore\Contracts\Repositories\Blocks as BlocksRepositoryContract;
use Folklore\Contracts\Repositories\Pages as PagesRepositoryContract;
use Folklore\Contracts\Resources\Page as PageContract;
use Folklore\Contracts\Resources\Resourcable;

class Pages extends Resources implements PagesRepositoryContract
{
    protected $blocks;

    protected $jsonAttributeFillable = '*';

    public function __construct(BlocksRepositoryContract $blocks)
    {
        $this->blocks = $blocks;
    }

    protected function newModel(): PageModel
    {
        return new PageModel();
    }

    protected function newQuery()
    {
        return parent::newQuery()->with('blocks');
    }

    public function findById(string $id): ?PageContract
    {
        return parent::findById($id);
    }

    public function findByHandle(string $handle): ?PageContract
    {
        $model = $this->newQueryWithParams()
            ->where('handle', $handle)
            ->first();
        return $model instanceof Resourcable ? $model->toResource() : $model;
    }

    public function findBySlug(string $slug, string $locale = null): ?PageContract
    {
        if (is_null($locale)) {
            $locale = app()->getLocale();
        }

        $model = $this->newQueryWithParams()
            ->where('slug_' . $locale, $slug)
            ->first();
        return $model instanceof Resourcable ? $model->toResource() : $model;
    }

    public function create($data): PageContract
    {
        return parent::create($data);
    }

    public function update(string $id, $data): ?PageContract
    {
        return parent::update($id, $data);
    }

    protected function saveData($model, $data)
    {
        if (isset($data['blocks'])) {
            $data['blocks'] = collect($data['blocks'])
                ->map(function ($item) {
                    return isset($item['id'])
                        ? $this->blocks->update($item['id'], $item)
                        : $this->blocks->create($item);
                })
                ->toArray();
        }

        parent::saveData($model, $data);

        if (isset($data['blocks'])) {
            $model->load('blocks');
        }
    }
}
