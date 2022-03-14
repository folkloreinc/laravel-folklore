<?php

namespace App\View\Composers;

use Folklore\Composers\Concerns\ComposesIntl;
use Folklore\Composers\Concerns\ComposesRoutes;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;

class AppComposer
{
    use ComposesIntl, ComposesRoutes;

    protected $routes = [
        'home',
    ];

    protected $routesLocalized = [];

    protected $translations = ['*'];

    /**
     * Create a new profile composer.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Bind data to the view.
     *
     * @param  \Illuminate\View\View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $locale = app()->getLocale();

        $user = $this->request->user();

        $props = [
            'user' => !is_null($user) ? new UserResource($user) : null,
            'routes' => $this->composeRoutesByNames(
                $view->routes ??
                    collect($this->getRoutesNamesWithLocales($this->routesLocalized))
                        ->merge($this->routes)
                        ->unique()
                        ->values(),
                $locale
            ),
            'intl' => $view->intl ?? [
                'locale' => app()->getLocale(),
                'locales' => config('locale.locales'),
                'messages' => $this->composesTranslations($this->translations, $locale),
            ],
        ];

        $view->props = array_merge($props, $view->props ?? []);
    }
}
