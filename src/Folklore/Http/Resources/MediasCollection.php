<?php

namespace Folklore\Http\Resources;

class MediasCollection extends Collection
{
    public $collects = MediaResource::class;

    /**
     * Each media in the shape of 1.1, whatever `site.medias.resource` says.
     */
    public function legacy(bool $legacy = true): static
    {
        $this->collection?->each(function (mixed $resource) use ($legacy) {
            if ($resource instanceof MediaResource) {
                $resource->legacy($legacy);
            }
        });

        return $this;
    }
}
