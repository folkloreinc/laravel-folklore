<?php

namespace Folklore\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Entities\Page as PageEntity;
use Folklore\JsonData\Contracts\HasJsonDataSchema;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Mediatheque\Concerns\HasMedias;
use Folklore\Models\Concerns\KeepsSubclassJsonDataRelations;
use Folklore\Models\Concerns\SluggableWithFallback;
use Folklore\Support\Concerns\HasTypedEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model implements HasJsonDataRelations, HasJsonDataSchema, ToEntity
{
    use HasMedias, HasTypedEntity, KeepsSubclassJsonDataRelations, Sluggable, SluggableWithFallback, SoftDeletes;

    protected $fillable = ['handle', 'type', 'parent_id', 'data', 'published'];

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    protected $entitiesByType = [];

    /**
     * The references of a page's data: its parent, its image and its blocks, the paths of the
     * 1.x declarations. They are relations, as in 1.x, so they write the key mapped to their
     * model (`media://` or `medias://` for the image) or else the relation's name. The root
     * keeps the keys it doesn't name. A subclass describes its own keys by adding to these
     * properties (docs/json-data.md, "Pages and blocks").
     */
    public function jsonDataSchema(string $attribute): ?ObjectType
    {
        if ($attribute !== 'data' || $this->overridesJsonDataRelations()) {
            return null;
        }

        return Json::object([
            'parent' => Json::relation('parent'),
            'image' => Json::relation('medias'),
            'blocks' => Json::list(Json::relation('blocks')),
        ])->additional(true);
    }

    /**
     * @deprecated The data is declared by `jsonDataSchema()`, which gives the same paths. A
     * subclass that overrides this method keeps its declarations; removed in 3.0.
     */
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
