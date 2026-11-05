<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Folklore\Panneau\BlockTypes;
use Folklore\Panneau\Fields\Blocks;
use Folklore\Panneau\Fields\Page;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Panneau\Fields\BlockType;
use Panneau\Fields\Image;
use Panneau\Fields\Text;
use Panneau\Support\Facade as Panneau;

/**
 * The pages, sections and images resources, the site's block types, and the localized routes
 * of pages, for the tests of the page fields (with ConformanceServiceProvider, for the auth).
 */
class PagesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(BlockTypes::class)->register(fn () => [
            BlockType::make('text', 'Text', [Text::make('body')]),
            BlockType::make('section', 'Section', [Page::make('section')->resource(SectionsResource::class)]),
            BlockType::make('stack', 'Stack', [Text::make('title'), Blocks::make('blocks')]),
            BlockType::make('image', 'Image', [Image::make('image')->resource(ImagesResource::class)]),
            BlockType::make('gallery', 'Gallery', [Image::make('images')->multiple()->resource(ImagesResource::class)]),
        ]);

        Panneau::resources([PagesResource::class, SectionsResource::class, ImagesResource::class]);

        foreach (['fr', 'en'] as $locale) {
            Route::get($locale, fn () => '')->name($locale.'.home');
            Route::get($locale.'/{page}', fn () => '')->name($locale.'.page');
            Route::get($locale.'/{parent}/{page}', fn () => '')->name($locale.'.page_with_parent');
            Route::get($locale.'/rubriques/{parent}/{page}', fn () => '')->name($locale.'.section_page');
        }
    }
}
