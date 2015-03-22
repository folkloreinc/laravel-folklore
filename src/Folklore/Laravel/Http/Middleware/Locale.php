<?php namespace Folklore\Laravel\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;

use App;
use Config;
use Session;

class Locale {

	/**
	 * Create a new filter instance.
	 *
	 * @param  Guard  $auth
	 * @return void
	 */
	public function __construct()
	{
		
	}

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		$locale = Session::has('locale') ? Session::get('locale'):'auto';
		$defaultLocale = Config::get('locale.locale');
		$locales = Config::get('locale.locales');
		
		if(Config::get('locale.detect_from_url'))
		{
			$segment = $request->segment(1);
			if($segment && in_array($segment, $locales))
			{
				$locale = $segment;
			}
		}
		
		if($locale !== 'auto')
	    {
	        App::setLocale($locale);
	    }
	    else
	    {
	        $browserLang = !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? strtok(strip_tags($_SERVER['HTTP_ACCEPT_LANGUAGE']), ',') : '';
	        $browserLang = substr($browserLang, 0, 2);
	        $userLang = in_array($browserLang, $locales) ? $browserLang : $defaultLocale;
	        App::setLocale($userLang);
	    }

		return $next($request);
	}

}
