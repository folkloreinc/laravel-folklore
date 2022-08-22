<?php

namespace  Folklore\Resources;

use Illuminate\Support\Collection;
use Folklore\Contracts\Resources\MediaFile as MediaFileContract;
use Folklore\Models\MediaFile as MediaFileModel;

class MediaFile implements MediaFileContract
{
    protected $model;

    public function __construct(MediaFileModel $model)
    {
        $this->model = $model;
    }

    public function id(): string
    {
        return $this->model->id;
    }

    public function handle(): ?string
    {
        return $this->model->handle;
    }

    public function url(): string
    {
        return $this->model->getUrl();
    }
}
