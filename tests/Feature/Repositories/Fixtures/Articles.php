<?php

namespace Folklore\Tests\Feature\Repositories\Fixtures;

use Folklore\Repositories\Concerns\ChecksSlugs;
use Folklore\Repositories\Entities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Articles, filtered by workspace, status and publication date, searched by title. Their
 * localized slug is given as `slug` (`['fr' => …, 'en' => …]`) and stored per locale.
 *
 * @extends Entities<Article, ArticleEntity>
 */
class Articles extends Entities implements ArticlesContract
{
    use ChecksSlugs;

    /**
     * @var list<string>
     */
    protected $queryColumns = ['workspace', 'status'];

    /**
     * @var list<string>
     */
    protected $orderableColumns = ['title', 'published_at', 'created_at'];

    public function findById(string $id): ?ArticleEntity
    {
        return parent::findById($id);
    }

    protected function newModel(): Model
    {
        return new Article;
    }

    /**
     * @param  Model  $model
     * @param  array<string, mixed>  $data
     * @return void
     */
    protected function fillModel($model, $data)
    {
        $slug = $data['slug'] ?? null;
        unset($data['slug']);
        if (is_array($slug)) {
            foreach (['fr', 'en'] as $locale) {
                if (array_key_exists($locale, $slug)) {
                    $data['slug_'.$locale] = $slug[$locale];
                }
            }
        }
        $model->fill($data);
    }

    /**
     * @param  Builder<Article>  $query
     * @param  array<string, mixed>  $params
     * @return Builder<Article>
     */
    protected function buildQueryFromParams($query, $params): Builder
    {
        $query = parent::buildQueryFromParams($query, $params);
        if (! $query instanceof Builder) {
            throw new LogicException('An Eloquent query was expected.');
        }
        if (isset($params['search']) && is_string($params['search'])) {
            $query->whereLike('title', '%'.$params['search'].'%');
        }
        // A range filter: its bounds, both inclusive
        $range = $params['published_at'] ?? null;
        if (is_array($range)) {
            if (isset($range['from']) && is_string($range['from'])) {
                $query->whereDate('published_at', '>=', $range['from']);
            }
            if (isset($range['to']) && is_string($range['to'])) {
                $query->whereDate('published_at', '<=', $range['to']);
            }
        }

        return $query;
    }
}
