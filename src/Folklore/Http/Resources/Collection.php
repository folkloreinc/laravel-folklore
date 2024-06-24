<?php

namespace Folklore\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Pagination\AbstractPaginator;

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

    // /**
    //  * Transform the resource into an array.
    //  *
    //  * @param  \Illuminate\Http\Request  $request
    //  * @return array
    //  */
    // public function toArray($request)
    // {
    //     return $this->resource instanceof AbstractPaginator
    //         ? [
    //             'data' => parent::toArray($request),
    //             'pagination' => new PaginationResource($this->resource),
    //         ]
    //         : parent::toArray($request);
    // }

    // /**
    //  * Create an HTTP response that represents the object.
    //  *
    //  * @param  \Illuminate\Http\Request  $request
    //  * @return \Illuminate\Http\JsonResponse
    //  */
    // public function toResponse($request)
    // {
    //     return (new ResourceResponse($this))->toResponse($request);
    // }
}
