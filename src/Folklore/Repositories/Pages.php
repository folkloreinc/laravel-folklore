<?php

namespace Folklore\Repositories;

use Folklore\Models\Page as PageModel;
use Folklore\Contracts\Repositories\Pages as PagesRepositoryContract;
use Folklore\Contracts\Resources\Page as PageContract;
use Folklore\Contracts\Resources\Resourcable;

class Pages extends Resources implements PagesRepositoryContract
{
    protected $jsonAttributeFillable = [
        'title',
        'image',
    ];

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
        $model = $this->newQuery()
            ->where('handle', $handle)
            ->first();
        return $model instanceof Resourcable ? $model->toResource() : $model;
    }

    public function findBySlug(string $slug, string $locale = null): ?PageContract
    {
        if (is_null($locale)) {
            $locale = app()->getLocale();
        }

        $model = $this->newQuery()
            ->where('slug_'.$locale, $slug)
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
}
