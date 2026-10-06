<?php

namespace Folklore\Http\Resources;

use Illuminate\Http\Request;

class LocalizedWithFallbackResource extends LocalizedResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $callback = $this->resource;
        $locales = $this->getLocales();

        return collect($locales)
            ->mapWithKeys(function ($locale) use ($callback) {
                return [
                    $locale => $callback($locale, false),
                ];
            })
            ->toArray();
    }
}
