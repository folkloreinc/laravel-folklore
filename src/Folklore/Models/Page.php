<?php

namespace Folklore\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Entities\Page as PageEntity;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Models\Concerns\SluggableWithFallback;
use Folklore\Support\Concerns\HasTypedEntity;
use Illuminate\Database\Eloquent\Model;

class Page extends Model implements HasJsonDataRelations, ToEntity
{
    use HasMedias, HasTypedEntity, Sluggable, SluggableWithFallback;

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

        return 'slug_'.$locale;
    }
}
