<?php

namespace Folklore\Repositories;

use Folklore\Models\Block as BlockModel;
use Folklore\Contracts\Repositories\Blocks as BlocksRepositoryContract;
use Folklore\Contracts\Resources\Block as BlockContract;
use Folklore\Contracts\Resources\Resourcable;

class Blocks extends Resources implements BlocksRepositoryContract
{
    protected $jsonAttributeFillable = '*';

    protected function newModel(): BlockModel
    {
        return new BlockModel();
    }

    protected function newQuery()
    {
        return parent::newQuery()->with('blocks');
    }

    public function findById(string $id): ?BlockContract
    {
        return parent::findById($id);
    }

    public function findByHandle(string $handle): ?BlockContract
    {
        $model = $this->newQueryWithParams()
            ->where('handle', $handle)
            ->first();
        return $model instanceof Resourcable ? $model->toResource() : $model;
    }

    public function create($data): BlockContract
    {
        return parent::create($data);
    }

    public function update(string $id, $data): ?BlockContract
    {
        return parent::update($id, $data);
    }

    protected function saveData($model, $data)
    {
        if (isset($data['blocks'])) {
            $data['blocks'] = collect($data['blocks'])
                ->map(function ($item) {
                    return isset($item['id'])
                        ? $this->update($item['id'], $item)
                        : $this->create($item);
                })
                ->toArray();
        }

        parent::saveData($model, $data);
    }
}
