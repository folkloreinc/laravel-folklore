<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\MediaFile as MediaFileContract;
use Folklore\Mediatheque\Contracts\Models\File as FileContract;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements HasModel<FileContract&Model>
 */
class MediaFile implements HasModel, MediaFileContract
{
    /**
     * @var FileContract&Model
     */
    protected $model;

    public function __construct(FileContract&Model $model)
    {
        $this->model = $model;
    }

    public function id(): string
    {
        $key = $this->model->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * The handle, with the index of the file in the 1.x form: `thumbnails.0`.
     */
    public function handle(): ?string
    {
        $index = $this->model->index();

        return is_null($index) ? $this->model->handle() : $this->model->handle().'.'.$index;
    }

    /**
     * The name of the uploaded file, or of the stored one.
     */
    public function name(): ?string
    {
        $filename = $this->model->metadata('filename');
        if (is_string($filename) && $filename !== '') {
            return $filename;
        }
        $path = $this->model->path();

        return is_null($path) ? null : basename($path);
    }

    public function url(): string
    {
        return $this->model->url() ?? '';
    }

    public function mime(): ?string
    {
        return $this->model->mime();
    }

    public function size(): ?int
    {
        return $this->model->size();
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
