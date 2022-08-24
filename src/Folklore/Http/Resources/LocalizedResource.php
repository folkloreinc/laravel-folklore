<?php

namespace Folklore\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LocalizedResource extends JsonResource
{
    protected $locales;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $callback = $this->resource;
        $locales = $this->getLocales();
        return collect($locales)
            ->mapWithKeys(function ($locale) use ($callback) {
                return [
                    $locale => $callback($locale),
                ];
            })
            ->toArray();
    }

    protected function getLocales()
    {
        return isset($this->locales) ? $this->locales : config('locale.locales');
    }

    public function withLocales($locales)
    {
        $this->locales = $locales;
        return $this;
    }
}
