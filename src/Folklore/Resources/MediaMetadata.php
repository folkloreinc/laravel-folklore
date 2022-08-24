<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\MediaMetadata as MediaMetadataContract;
use Folklore\Models\Media as MediaModel;

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
        $metadata = $this->getMetadatas()->get('description');
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
