<?php

namespace App\Providers;

use App\Repositories\Articles;
use Folklore\Site\Manager;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Panneau\Contracts\Repository;

// use Folklore\Contracts\Entities\Contact;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Response::macro(
            'csv',
            fn ($rows, $filename) => response()->streamDownload(fn () => print (''), $filename)
        );

        /*
         * Folklore\Contracts\Entities\Person
         */
        $this->app->bind(Repository::class, Articles::class);
        $this->app->singleton(Manager::class);
    }
}
