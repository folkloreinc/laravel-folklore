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
		$this->bootPublishes();
	}
	
	protected function bootPublishes()
	{
		$configPath = __DIR__ . '/../../resources/config/locale.php';
		
		$this->mergeConfigFrom($configPath, 'locale');
		
		$this->publishes([
			$configPath => config_path('locale.php')
		], 'folklore.locale.config');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->registerMiddlewares();
		$this->registerEventHandlers();
	}
	
	public function registerMiddlewares()
	{
		$http = $this->app['Illuminate\Contracts\Http\Kernel'];
		$http->pushMiddleware('Folklore\Laravel\Http\Middleware\Locale');
	}
	
	public function registerEventHandlers()
	{
		$this->app['events']->subscribe('Folklore\Laravel\Events\LocaleEventHandler');
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
