<?php

namespace Folklore\Resources;

use Illuminate\Support\Collection;
use Folklore\Contracts\Resources\MediaMetadata as MediaMetadataContract;
use Folklore\Models\Media as MediaModel;
use App\Contracts\User as UserContract;

class MediaMetadata implements MediaMetadataContract
{
    protected $model;

    protected $file;

    protected $metadatas;

    protected $tags;

    public function __construct(MediaModel $model)
    {
        $this->model = $model;
    }

    public function filename(): ?string
    {
        return $this->getOriginalFile()->name;
    }

    public function size(): ?int
    {
        return $this->getOriginalFile()->size;
    }

    public function mime(): ?string
    {
        return $this->getOriginalFile()->mime;
    }

    public function description(): ?string
    {
        $metadata = $this->getMetadatas()->get('descriptions');
        return !is_null($metadata) ? $metadata->getValue() : null;
    }

    protected function getOriginalFile()
    {
        if (!isset($this->originalFile)) {
            $this->originalFile = $this->model->getFile('original');
        }
        return $this->originalFile;
    }

    protected function getMetadatas()
    {
        if (!isset($this->metadatas)) {
            $this->metadatas = $this->model->getMetadatas();
        }
        return $this->metadatas;
    }
}
