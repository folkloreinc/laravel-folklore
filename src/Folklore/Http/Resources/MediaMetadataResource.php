<?php

namespace Folklore\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Folklore\Contracts\Resources\AudioMetadata;
use Folklore\Contracts\Resources\ImageMetadata;
use Folklore\Contracts\Resources\VideoMetadata;
use Folklore\Contracts\Resources\DocumentMetadata;

class MediaMetadataResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $hasDuration =
            $this->resource instanceof AudioMetadata || $this->resource instanceof VideoMetadata;
        $hasSize =
            $this->resource instanceof VideoMetadata || $this->resource instanceof ImageMetadata;
        $hasPagesCount = $this->resource instanceof DocumentMetadata;
        return [
            'filename' => $this->filename(),
            'size' => $this->size(),
            'mime' => $this->mime(),
            'duration' => $this->when($hasDuration, function () {
                return $this->duration();
            }),
            'pagesCount' => $this->when($hasPagesCount, function () {
                return $this->duration();
            }),
            $this->mergeWhen($hasSize, function () {
                return [
                    'width' => $this->width(),
                    'height' => $this->height(),
                ];
            }),
        ];
    }
}
