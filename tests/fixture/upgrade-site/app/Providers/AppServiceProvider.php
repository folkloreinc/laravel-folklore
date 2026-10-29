<?php

namespace App\Providers;

use App\Repositories\Articles;
use App\Routing\UrlGeneratorMixin;
use Folklore\Site\Manager;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\ServiceProvider;
use Panneau\Contracts\Repository;

// use Folklore\Contracts\Entities\Contact;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        UrlGenerator::macro(
            'routeForReactRouter',
            $this->app->make(UrlGeneratorMixin::class)->routeForReactRouter()
        );

        /*
         * Folklore\Contracts\Entities\Person
         */
        $this->app->bind(Repository::class, Articles::class);
        $this->app->singleton(Manager::class);
    }

    public function routes(): string
    {
        return url()->routeForReactRouter('home');
    }
}
