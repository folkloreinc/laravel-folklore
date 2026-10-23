<?php

namespace Folklore\Entities;

use Carbon\Carbon;
use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\Media as MediaContract;
use Folklore\Contracts\Entities\MediaFile as MediaFileContract;
use Folklore\Contracts\Entities\MediaMetadata as MediaMetadataContract;
use Folklore\Mediatheque\Contracts\Models\File as FileContract;
use Folklore\Models\Media as MediaModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @implements HasModel<MediaModel>
 */
class Media implements HasModel, MediaContract
{
    /**
     * @var MediaModel
     */
    protected $model;

    /**
     * @var Collection<int, MediaFile>|null
     */
    protected $files;

    protected $metadata;

    protected $thumbnailUrl;

    public function __construct(MediaModel $model)
    {
        $this->model = $model;
    }

    /**
     * The internal id, which `medias://` references and the 1.x JSON shape use.
     */
    public function id(): string
    {
        $key = $this->model->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * The public id (`med_…`), which `media://` references use.
     */
    public function uid(): string
    {
        return $this->model->uid();
    }

    public function type(): string
    {
        return $this->model->type();
    }

    public function name(): string
    {
        return $this->model->name() ?? '';
    }

    public function url(): string
    {
        $file = $this->getOriginalFile() ?? $this->files()->first();

        return ! is_null($file) ? $file->url() : '';
    }

    public function thumbnailUrl(): ?string
    {
        if (! isset($this->thumbnailUrl)) {
            $thumbnailFile = $this->files()->first(function ($file) {
                return preg_match('/^(thumbnail|poster)/', (string) $file->handle()) === 1;
            });
            if (! is_null($thumbnailFile)) {
                $this->thumbnailUrl = $thumbnailFile->url();
            } elseif ($this->type() === 'image') {
                $this->thumbnailUrl = $this->url();
            }
        }

        return $this->thumbnailUrl;
    }

    /**
     * @return Collection<int, MediaFile>
     */
    public function files(): Collection
    {
        if (! isset($this->files)) {
            $this->files = $this->model->files()
                ->filter(fn (FileContract $file) => $file instanceof Model)
                ->map(fn (FileContract&Model $file) => new MediaFile($file))
                ->values();
        }

        return $this->files;
    }

    public function metadata(): MediaMetadataContract
    {
        if (! isset($this->metadata)) {
            $this->metadata = new MediaMetadata($this->model);
        }

        return $this->metadata;
    }

    protected function getOriginalFile(): ?MediaFileContract
    {
        return $this->files()->first(function ($file) {
            return $file->handle() === FileContract::ORIGINAL;
        });
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function createdAt(): ?Carbon
    {
        return $this->model->created_at;
    }

    public function updatedAt(): ?Carbon
    {
        return $this->model->updated_at;
    }
}
