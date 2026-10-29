<?php

namespace Folklore\Tests\Feature\Repositories\Fixtures;

use Folklore\Contracts\Entities\HasEntityKey;
use Folklore\Contracts\Entities\ToEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * An article of a workspace, with a slug per locale (`slug_fr`, `slug_en`), found by its uid.
 *
 * @implements ToEntity<ArticleEntity>
 */
class Article extends Model implements HasEntityKey, ToEntity
{
    use SoftDeletes;

    protected $table = 'articles';

    protected $fillable = ['workspace', 'title', 'slug_fr', 'slug_en', 'status', 'published_at'];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Article $article) {
            $article->setAttribute('uid', $article->getAttribute('uid') ?? 'art_'.Str::lower((string) Str::ulid()));
        });
    }

    public function getEntityKeyName(): string
    {
        return 'uid';
    }

    public function getEntityKey(): mixed
    {
        return $this->getAttribute($this->getEntityKeyName());
    }

    public function toEntity(): ArticleEntity
    {
        return new ArticleEntity($this);
    }
}
