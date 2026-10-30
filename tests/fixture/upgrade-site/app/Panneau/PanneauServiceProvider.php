<?php

namespace App\Panneau;

use Folklore\Panneau\Fields\PageSlug as PageSlugField;
use Folklore\Panneau\Fields\PageSlugLocalized;
use Illuminate\Support\ServiceProvider;

class PanneauServiceProvider extends ServiceProvider
{
    protected $fields = [PageSlugLocalized::class];

    public function boot(): void
    {
        $this->app->booted(function () {
            PageSlugField::setRoutesResolver(fn ($locale) => [
                'page' => "/{$locale}/:slug",
            ]);
        });
    }
}
