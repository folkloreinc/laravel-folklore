<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Folklore\Tests\Feature\Panneau\Fixtures\Site\Medias;
use Folklore\Tests\Feature\Panneau\Fixtures\Site\Pages;
use Folklore\Tests\Feature\Panneau\Fixtures\Site\Users;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Panneau\Support\Facade as Panneau;

/**
 * A site's standard resources, empty classes that extend the package's, and the localized
 * routes of its pages (with ConformanceServiceProvider, for the auth).
 */
class StandardResourcesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Panneau::resources([Pages::class, Users::class, Medias::class]);

        foreach (['fr', 'en'] as $locale) {
            Route::get($locale, fn () => '')->name($locale.'.home');
            Route::get($locale.'/{page}', fn () => '')->name($locale.'.page');
            Route::get($locale.'/{parent}/{page}', fn () => '')->name($locale.'.page_with_parent');
        }
    }
}
