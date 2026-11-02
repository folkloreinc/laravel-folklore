<?php

namespace Folklore\Tests\Feature\Pages;

use Folklore\Entities\Page;
use Folklore\Models\Page as PageModel;
use Folklore\Pages\PageRoutes;
use Folklore\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * The routes of pages that `site.pages.routes` names, with or without their locale.
 */
class PageRoutesTest extends TestCase
{
    private function refreshRoutes(): void
    {
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    private function page(?string $handle = null, ?PageModel $parent = null): Page
    {
        $model = new PageModel;
        $model->forceFill(['handle' => $handle, 'slug_fr' => 'ete']);
        $model->setRelation('parent', $parent);

        return new Page($model);
    }

    public function test_routes_without_a_locale_are_found()
    {
        Route::get('/', fn () => '')->name('home');
        Route::get('/{page}', fn () => '')->name('page');
        $this->refreshRoutes();

        $this->assertSame(url('ete'), PageRoutes::page('ete', 'fr'));
        $this->assertSame(url('/'), PageRoutes::home('fr'));
        $this->assertNull(PageRoutes::page('ete', 'fr', 'vacances'));

        $this->assertSame('/ete', $this->page()->url('fr'));
        $this->assertSame(url('/'), $this->page('home')->url('fr', true));
        $this->assertSame('page', PageRoutes::routeName('page', 'fr'));
    }

    public function test_routes_with_the_locale_come_first()
    {
        Route::get('/{page}', fn () => '')->name('page');
        Route::get('fr/{page}', fn () => '')->name('fr.page');
        Route::get('fr/{parent}/{page}', fn () => '')->name('fr.page_with_parent');
        $this->refreshRoutes();

        $this->assertSame('fr.page', PageRoutes::routeName('page', 'fr'));
        $this->assertSame(url('fr/ete'), PageRoutes::page('ete', 'fr'));
        $this->assertSame('page', PageRoutes::routeName('page', 'en'));

        $parent = new PageModel;
        $parent->forceFill(['slug_fr' => 'vacances']);
        $this->assertSame('/fr/ete', $this->page()->url('fr'));
        $this->assertSame('/fr/vacances/ete', $this->page(null, $parent)->url('fr'));
    }

    public function test_a_missing_route_of_a_page_is_not_found()
    {
        $this->assertNull(PageRoutes::routeName('page', 'fr'));

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('[fr.page]');
        $this->page()->url('fr');
    }
}
