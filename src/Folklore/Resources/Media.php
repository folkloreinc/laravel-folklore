<?php

namespace Folklore\Resources;

use Illuminate\Support\Collection;
use Folklore\Contracts\Resources\Media as MediaContract;
use Folklore\Contracts\Resources\Image as ImageContract;
use Folklore\Contracts\Resources\MediaFile as MediaFileContract;
use Folklore\Contracts\Resources\MediaMetadata as MediaMetadataContract;
use Folklore\Models\Media as MediaModel;
use App\Contracts\Resources\Resourcable;

class Media implements MediaContract
{
    protected $model;

    protected $files;

    protected $metadata;

    protected $thumbnailUrl;

    public function __construct(MediaModel $model)
    {
        $this->model = $model;
    }

    public function id(): string
    {
        return $this->model->id;
    }

    public function type(): string
    {
        return $this->model->type;
    }

    public function name(): string
    {
        return $this->model->name;
    }

    public function url(): string
    {
        $originalFile = $this->getOriginalFile();
        return $originalFile->url();
    }

    public function thumbnailUrl(): ?string
    {
        if (!isset($this->thumbnailUrl)) {
            $thumbnailFile = $this->files()->first(function ($file) {
                return preg_match('/^thumbnail/', $file->handle()) === 1;
            });
            if (!is_null($thumbnailFile)) {
                $this->thumbnailUrl = $thumbnailFile->url();
            } elseif ($this->type() === 'image') {
                $this->thumbnailUrl = $this->url();
            }
        }

        return $this->thumbnailUrl;
    }

    public function files(): Collection
    {
        if (!isset($this->files)) {
            $this->files = $this->model->files->map(function ($item) {
                return $item instanceof Resourcable ? $item->toResource() : $item;
            });
        }
        return $this->files;
    }

    public function metadata(): MediaMetadataContract
    {
        if (!isset($this->metadata)) {
            $this->metadata = new MediaMetadata($this->model);
        }
        return $this->metadata;
    }

    protected function getOriginalFile(): MediaFileContract
    {
        return $this->files()->first(function ($file) {
            return $file->handle() === 'original';
        });
    }
}
