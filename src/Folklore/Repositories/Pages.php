<?php

namespace Folklore\Repositories;

use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Repositories\Blocks as BlocksRepositoryContract;
use Folklore\Contracts\Repositories\Pages as PagesRepositoryContract;
use Folklore\Models\Page as PageModel;

class Pages extends Entities implements PagesRepositoryContract
{
    protected $blocks;

    protected $jsonAttributeFillable = '*';

    public function __construct(BlocksRepositoryContract $blocks)
    {
        $this->blocks = $blocks;
    }

    protected function newModel(): PageModel
    {
        return new PageModel;
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

        return to_entity($model);
    }

    /**
     * Unpublished pages are returned too. To only find published pages, set
     * the `published` param: `$pages->setGlobalQuery(['published' => true])`.
     */
    public function findBySlug(string $slug, ?string $locale = null): ?PageContract
    {
        if (is_null($locale)) {
            $locale = app()->getLocale();
        }

        $model = $this->newQueryWithParams()
            ->where('slug_'.$locale, $slug)
            ->first();

        return to_entity($model);
    }

    public function create($data): PageContract
    {
        return parent::create($data);
    }

    public function update(string $id, $data): ?PageContract
    {
        return parent::update($id, $data);
    }

    /**
     * Besides the common params, `published` (a boolean, or a boolean string
     * such as "1" or "false" from a request) only keeps published or
     * unpublished pages. Without it, pages are returned whatever their state.
     */
    protected function buildQueryFromParams($query, $params)
    {
        $query = parent::buildQueryFromParams($query, $params);

        $published = isset($params['published']) && $params['published'] !== ''
            ? filter_var($params['published'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        if (! is_null($published)) {
            $query->where('published', $published);
        }

        return $query;
    }

    /**
     * Save the page and its blocks in a single transaction.
     */
    protected function saveData($model, $data)
    {
        $model->getConnection()->transaction(function () use ($model, $data) {
            $this->saveBlocksAndData($model, $data);
        });

        if (isset($data['blocks'])) {
            $model->load('blocks');
        }
    }

    protected function saveBlocksAndData($model, $data)
    {
        if (isset($data['blocks'])) {
            $data['blocks'] = collect($data['blocks'])
                ->map(function ($item) use ($model) {
                    $id = data_get($item, 'id');
                    if (isset($item['handle']) && is_null($id)) {
                        $id = $model
                            ->blocks()
                            ->where('handle', $item['handle'])
                            ->value('blocks.id');
                    }

                    return ! empty($id)
                        ? $this->blocks->update($id, $item)
                        : $this->blocks->create($item);
                })
                ->filter(function ($block) {
                    return ! is_null($block);
                })
                ->values()
                ->toArray();
        }

        parent::saveData($model, $data);
    }
}
