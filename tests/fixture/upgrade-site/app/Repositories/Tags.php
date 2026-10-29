<?php

namespace App\Repositories;

use Folklore\Repositories\Entities;

class Tags extends Entities
{
    protected function syncRelations($model, $data = [])
    {
        $model->articles()->sync($data['articles'] ?? []);
    }

    public function forSite($site)
    {
        $this->setGlobalQuery(['site' => $site]);

        return $this;
    }
}
