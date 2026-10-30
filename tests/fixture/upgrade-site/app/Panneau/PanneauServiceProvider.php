<?php

namespace App\Panneau;

use Folklore\Panneau\Fields\PageSlug as PageSlugField;
use Illuminate\Support\ServiceProvider;

class PanneauServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            PageSlugField::setRoutesResolver(fn ($locale) => [
                'page' => "/{$locale}/:slug",
            ]);
        });
    }
}
