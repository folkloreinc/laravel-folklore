<?php

namespace Folklore\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Entities\Page as PageEntity;
use Folklore\JsonData\Contracts\HasJsonDataSchema;
use Folklore\JsonData\JsonDataCast;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\JsonData\Schema\Type;
use Folklore\Mediatheque\Concerns\HasMedias;
use Folklore\Models\Concerns\SluggableWithFallback;
use Folklore\Pages\BlockRegistry;
use Folklore\Support\Concerns\HasTypedEntity;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model implements HasJsonDataSchema, ToEntity
{
    use HasMedias, HasTypedEntity, Sluggable, SluggableWithFallback, SoftDeletes;

    protected $fillable = ['handle', 'type', 'parent_id', 'data', 'published'];

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    protected $entitiesByType = [];

    /**
     * The data of a page (docs/decisions/0023): what its entity reads, its title,
     * description, slug, image and parent, and its blocks, typed by `site.pages.blocks`.
     * The root keeps other keys unless the map is enforced: a site's page model adds its
     * own properties to these (`...parent::jsonDataSchema($attribute)?->getProperties()`),
     * and closes it.
     */
    public function jsonDataSchema(string $attribute): ?ObjectType
    {
        if ($attribute !== 'data') {
            return null;
        }

        $blocks = Container::getInstance()->make(BlockRegistry::class)->union();

        return Json::object([
            'title' => Json::string()->translatable(),
            'description' => Json::string()->translatable(),
            'slug' => Json::string()->translatable(),
            'image' => self::media(),
            'parent' => Json::reference('page')->relation('parent'),
            'blocks' => Json::list($blocks ?? Json::any()),
        ]);
    }

    /**
     * `Json::media()` with the medias module, or else the 1.x reference of the `medias`
     * relation of `HasMedias`.
     */
    protected static function media(): Type
    {
        $media = Json::hasMacro('media') ? Json::__callStatic('media', []) : null;

        return $media instanceof Type ? $media : Json::relation('medias');
    }

    /**
     * The model of the pages, `site.pages.model`: the key `page` is mapped to it.
     *
     * @return class-string<Page>
     */
    public static function pageModel(): string
    {
        $model = config('site.pages.model');

        return is_string($model) && is_a($model, self::class, true) ? $model : self::class;
    }

    public function parent()
    {
        return $this->belongsTo(static::pageModel(), 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(static::pageModel(), 'parent_id');
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
