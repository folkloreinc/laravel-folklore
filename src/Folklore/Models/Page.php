<?php

namespace Folklore\Models;

use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Contracts\Resources\Page as PageContract;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Resources\Page as PageResource;
use Folklore\Models\Concerns\SluggableWithFallback;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Support\Concerns\HasTypedResource;

class Page extends Model implements Resourcable, HasJsonDataRelations
{
    use Sluggable, SluggableWithFallback, HasMedias, HasTypedResource;

    protected $fillable = ['handle', 'type', 'parent_id', 'data', 'published'];

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    protected $typedResources = [];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return [
            'parent' => 'parent',
            'data.image' => 'medias',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id');
    }

    public function blocks()
    {
        return $this->morphToMany(Block::class, 'blockable', 'blocks_pivot');
    }

    /**
     * To resource
     */
    public function toResource(): PageContract
    {
        return $this->toTypedResource() ?? new PageResource($this);
    }

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable(): array
    {
        return $this->handle === 'home'
            ? []
            : $this->getSluggablesWithFallback('data.title.%s', 'slug_%s', 'data.slug.%s', [
                'unique' => false,
            ]);
    }

    public function getRouteKeyName()
    {
        $locale = app()->getLocale();
        return 'slug_' . $locale;
    }
}
