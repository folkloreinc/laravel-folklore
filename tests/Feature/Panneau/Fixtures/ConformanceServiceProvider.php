<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Folklore\Panneau\PanneauServiceProvider;
use Folklore\Tests\Feature\Repositories\Fixtures\Articles;
use Folklore\Tests\Feature\Repositories\Fixtures\ArticlesContract;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Panneau\Support\Facade as Panneau;

/**
 * An app with a Panneau resource on a Folklore repository, behind a bearer token
 * (`Authorization: Bearer conformance`). The tests of the panneau module use it, and CI serves
 * it to the protocol's conformance kit (testbench.yaml).
 */
class ConformanceServiceProvider extends ServiceProvider
{
    public const TOKEN = 'conformance';

    public function register(): void
    {
        $config = $this->app['config'];
        // The panneau module, which `testbench serve` may register after the package's provider
        $config->set('site.modules.panneau', true);
        $this->app->register(PanneauServiceProvider::class);
        $config->set('auth.guards.conformance', ['driver' => 'conformance-token']);
        $config->set('panneau.auth.guard', 'conformance');
        // A token, not a session: no "web" middleware, so no CSRF token to send
        $config->set('panneau.middleware', ['panneau.auth']);
        $config->set('panneau.locales', ['fr', 'en']);

        $this->app->bind(ArticlesContract::class, Articles::class);
    }

    public function boot(): void
    {
        Auth::viaRequest('conformance-token', fn (Request $request): ?GenericUser => $request->bearerToken() === self::TOKEN
            ? new GenericUser(['id' => 1, 'name' => 'Conformance'])
            : null);
        Gate::define('viewPanneau', fn ($user): bool => true);

        Panneau::allowWithoutPolicies();
        Panneau::resources([ArticlesResource::class]);
        Panneau::routes();
    }
}
