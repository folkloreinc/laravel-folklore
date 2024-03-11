<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\Document as DocumentContract;
use Folklore\Contracts\Resources\DocumentMetadata as DocumentMetadataContract;
use Illuminate\Support\Collection;

class Document extends Media implements DocumentContract
{
    protected $thumbnailFile;

    public function metadata(): DocumentMetadataContract
    {
        if (!isset($this->metadata)) {
            $this->metadata = new DocumentMetadata($this->model);
        }
        return $this->metadata;
    }

    public function thumbnailUrl(): ?string
    {
        if (!isset($this->thumbnailUrl)) {
            $thumbnailFile = $this->thumbnailFile();
            if (!is_null($thumbnailFile)) {
                $this->thumbnailUrl = $thumbnailFile->url();
            } elseif ($this->type() === 'image') {
                $this->thumbnailUrl = $this->url();
            }
        }
        return $this->thumbnailUrl;
    }

    public function thumbnailFile()
    {
        if (!isset($this->thumbnailFile)) {
            $this->thumbnailFile = $this->files()->first(function ($file) {
                return preg_match('/^thumbnail/', $file->handle()) === 1 ||
                    preg_match('/thumbnails-0/', $file->name()) === 1;
            });
        }
        return $this->thumbnailFile;
    }
}
