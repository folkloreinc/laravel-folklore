<?php

namespace Folklore\Http\Resources;

use Folklore\Support\OffsetPaginator;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->resource instanceof OffsetPaginator ? [
            'offset' => $this->currentOffset(),
            'next_offset' => $this->nextOffset(),
            'count' => $this->count(),
            'total' => $this->total(),
            'is_last' => $this->total() === $this->nextOffset()
        ] : [
            'page' => $this->currentPage(),
            'last_page' => $this->lastPage(),
            'per_page' => $this->perPage(),
            'count' => $this->count(),
            'total' => $this->total(),
            'is_last' => $this->currentPage() === $this->lastPage()
        ];
    }
}
