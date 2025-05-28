<?php

namespace Folklore\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class Collection extends ResourceCollection
{
    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        return [];
    }

    public function paginationInformation()
    {
        return [
            'pagination' => new PaginationResource($this->resource),
        ];
    }
}
