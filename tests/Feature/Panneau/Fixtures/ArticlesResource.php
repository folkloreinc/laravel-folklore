<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Folklore\Tests\Feature\Repositories\Fixtures\ArticleEntity;
use Folklore\Tests\Feature\Repositories\Fixtures\ArticlesContract;
use Illuminate\Http\Request;
use Panneau\Fields\DateTime;
use Panneau\Fields\Select;
use Panneau\Fields\Slug;
use Panneau\Fields\Text;
use Panneau\Lists\Filters\DateRangeFilter;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;

/**
 * A resource on a Folklore repository: it points to the repository's contract, and the
 * panneau module adapts it.
 */
class ArticlesResource extends Resource
{
    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    public function id(): string
    {
        return 'articles';
    }

    public function repository(): string
    {
        return ArticlesContract::class;
    }

    public function itemClass(): string
    {
        return ArticleEntity::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->label('Title')->required()->max(120),
            Slug::make('slug')->from('title')->localized()->unique()
                ->url(fn (string $slug, ?string $locale): string => 'https://example.com/'.$locale.'/articles/'.$slug),
            Select::make('status')->options(self::STATUSES)->required()->default('draft'),
            DateTime::make('published_at'),
        ];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list
            ->columns(['title', 'status', 'published_at'])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES)->multiple(),
                DateRangeFilter::make('published_at'),
                // A filter on a param of the scope, which the adapter ignores
                SelectFilter::make('workspace')->options(['folklore' => 'Folklore', 'urbania' => 'Urbania']),
            ])
            ->sorts(['title', 'published_at'])
            ->defaultSort('-published_at')
            ->search(['title']);
    }

    public function scope(Request $request): array
    {
        return ['workspace' => 'folklore'];
    }

    /**
     * The values that put a new article within the scope: the adapter doesn't write the
     * scope's params, which are the repository's.
     */
    public function creating(array $values, Request $request): array
    {
        return array_merge($values, $this->scope($request));
    }

    public function toItem(object $item): array
    {
        if (! $item instanceof ArticleEntity) {
            return [];
        }

        return [
            'title' => $item->title(),
            'slug' => ['fr' => $item->slug('fr'), 'en' => $item->slug('en')],
            'status' => $item->status(),
            'published_at' => $item->publishedAt(),
        ];
    }
}
