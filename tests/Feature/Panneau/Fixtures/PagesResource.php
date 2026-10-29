<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Repositories\Pages;
use Folklore\Panneau\Fields\Blocks;
use Folklore\Panneau\Fields\Page;
use Folklore\Panneau\Fields\PageSlug;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;

/**
 * The pages resource, on the pages module's repository, with the page fields of the panneau
 * module.
 */
class PagesResource extends Resource
{
    public const TYPES = ['page' => 'Page', 'section' => 'Section'];

    public function repository(): string
    {
        return Pages::class;
    }

    public function itemClass(): string
    {
        return PageContract::class;
    }

    public function fields(): array
    {
        return [
            // Read through the field, which gives the label of the references to a page
            Text::make('title')->localized()->resolveUsing(
                fn (PageContract $page): array => ['fr' => $page->title('fr'), 'en' => $page->title('en')],
            ),
            PageSlug::make('slug'),
            Select::make('type')->options(self::TYPES),
            Page::make('parent'),
            Blocks::make('blocks'),
        ];
    }

    public function toItem(object $item): array
    {
        if (! $item instanceof PageContract) {
            return [];
        }

        return [
            'title' => $this->field('title')?->resolve($item),
            'slug' => $this->field('slug')?->resolve($item),
            'type' => $item->type(),
            'parent' => $item->parent(),
            'blocks' => $this->field('blocks')?->resolve($item),
        ];
    }
}
