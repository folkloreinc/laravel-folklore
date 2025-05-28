<?php

namespace Folklore\Models;

use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Entities\Page as PageEntity;
use Folklore\Models\Concerns\SluggableWithFallback;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Support\Concerns\HasTypedEntity;

class Page extends Model implements ToEntity, HasJsonDataRelations
{
    use Sluggable, SluggableWithFallback, HasMedias, HasTypedEntity;

    protected $fillable = ['handle', 'type', 'parent_id', 'data', 'published'];

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    protected $entitiesByType = [];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return [
            'parent' => 'parent',
            'image' => 'medias',
            'blocks.*' => 'blocks',
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
     * To entity
     */
    public function toEntity(): PageContract
    {
        return $this->toTypedEntity() ?? new PageEntity($this);
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
