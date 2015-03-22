<?php namespace Folklore\Laravel\Events;

use App;
use View;
use Config;
use session;

class LocaleEventHandler {

    /**
     * Handle user login events.
     */
    public function onRouteMatched($route, $request)
    {
        $currentLocale = Config::get('app.locale');
        $action = $route->getAction();
        $locales = Config::get('locale.locales');
        if(isset($action['locale']) && $action['locale'] !== $currentLocale && in_array($action['locale'], $locales))
		{
			App::setLocale($action['locale']);
		}
    }
    
    public function onLocaleChanged($currentLocale)
    {
        Session::put('locale', $currentLocale);
        
        $otherLocales = array();
        $locales = Config::get('locale.locales');
        foreach($locales as $locale)
        {
            if($locale !== $currentLocale)
            {
                $otherLocales[] = $locale;
            }
        }
        View::share('locale', $currentLocale);
        View::share('otherLocales', $otherLocales);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  Illuminate\Events\Dispatcher  $events
     * @return array
     */
    public function subscribe($events)
    {
        $events->listen('router.matched', 'Folklore\Laravel\Events\LocaleEventHandler@onRouteMatched');
        $events->listen('locale.changed', 'Folklore\Laravel\Events\LocaleEventHandler@onLocaleChanged');
    }

}
