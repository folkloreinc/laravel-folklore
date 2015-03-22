<?php namespace Folklore\Laravel;

use Illuminate\Support\ServiceProvider;

use View;
use Config;

class LocaleServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->initPublishes();
		
		$this->app['events']->subscribe('Folklore\Laravel\Events\LocaleEventHandler');
	}
	
	protected function initPublishes()
	{
		$configPath = __DIR__ . '/../../resources/config/locale.php';
		$translationsPath = __DIR__ . '/../../resources/translations';
		
		$this->mergeConfigFrom($configPath, 'locale');
		
		$this->loadTranslationsFrom($translationsPath, 'folklore');
		
		$this->publishes([
			$configPath => config_path('locale.php')
		], 'config');
		
		$this->publishes([
			$translationsPath => base_path('resources/lang/packages')
		], 'translations');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->registerMiddlewares();
	}
	
	public function registerMiddlewares()
	{
		$http = $this->app['Illuminate\Contracts\Http\Kernel'];
		$http->pushMiddleware('Folklore\Laravel\Http\Middleware\Locale');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('folklore.locale');
	}

}
