<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\MediaMetadata as MediaMetadataContract;
use Folklore\Mediatheque\Contracts\Models\File as FileContract;
use Folklore\Models\Media as MediaModel;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements HasModel<MediaModel>
 */
class MediaMetadata implements HasModel, MediaMetadataContract
{
    /**
     * @var MediaModel
     */
    protected $model;

    /**
     * @var FileContract|null
     */
    protected $originalFile;

    public function __construct(MediaModel $model)
    {
        $this->model = $model;
    }

    public function filename(): ?string
    {
        $filename = $this->value('filename');
        if (is_string($filename) && $filename !== '') {
            return $filename;
        }
        $path = $this->getOriginalFile()?->path();

        return is_null($path) ? null : basename($path);
    }

    public function size(): ?int
    {
        return $this->getOriginalFile()?->size();
    }

    public function mime(): ?string
    {
        return $this->getOriginalFile()?->mime();
    }

    public function description(): ?string
    {
        $description = $this->value('description');

        return is_string($description) ? $description : null;
    }

    protected function getOriginalFile(): ?FileContract
    {
        if (! isset($this->originalFile)) {
            $this->originalFile = $this->model->original();
        }

        return $this->originalFile;
    }

    /**
     * A metadata value of the media, or of its original file.
     */
    protected function value(string $key): mixed
    {
        return $this->model->metadata($key) ?? $this->getOriginalFile()?->metadata($key);
    }

    /**
     * A numeric metadata value of the media, or of its original file.
     */
    protected function number(string $key): int|float|null
    {
        $value = $this->value($key);

        return is_int($value) || is_float($value) ? $value : (is_numeric($value) ? (float) $value : null);
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
