<?php namespace Folklore\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class FolkloreServiceProvider extends ServiceProvider {

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
		
		// Config file path
		$configPath = __DIR__ . '/../../resources/config/folklore.php';
		$publicPath = __DIR__ . '/../../resources/public/';
		$assetsPath = __DIR__ . '/../../resources/assets/';
		$viewsPath = __DIR__ . '/../../resources/views/';

		// Merge files
		$this->mergeConfigFrom($configPath, 'folklore');
		$this->loadViewsFrom($viewsPath, 'folklore');

		// Publish
		$this->publishes([
			$configPath => config_path('folklore.php')
		], 'config');
		
		$this->publishes([
	        $viewsPath => base_path('resources/views/vendor/folklore'),
	    ], 'views');
		
		$this->publishes([
			$publicPath => public_path(),
			$assetsPath => base_path('resources/assets'),
		], 'public');

		$app = $this->app;
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->registerFolklore();
		$this->registerAsset();
		$this->registerLocale();
		$this->registerConsoleCommands();
		$this->registerComposers();
	}
	
	public function registerFolklore()
	{
		$this->app->singleton('folklore', function($app)
		{
			return new Folklore($app);
		});
	}
	
	public function registerLocale()
	{
		$this->app->register('Folklore\Laravel\LocaleServiceProvider');
	}
	
	public function registerAsset()
	{
		$this->app->register('Orchestra\Asset\AssetServiceProvider');
    	$this->app->register('Orchestra\Html\HtmlServiceProvider');
		
		$loader = AliasLoader::getInstance();
    	$loader->alias('Asset', 'Orchestra\Support\Facades\Asset');
    	$loader->alias('HTML', 'Orchestra\Support\Facades\HTML');
	}
	
	public function registerConsoleCommands()
	{
		$this->commands('Folklore\Laravel\Console\Commands\FolkloreInstallCommand');
	}
	
	public function registerComposers()
	{
		$view = $this->app['view'];
		$view->composer('folklore::layouts.main', 'Folklore\Laravel\View\Composers\LayoutComposer');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('folklore');
	}

}
