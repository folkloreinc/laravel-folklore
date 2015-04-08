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
		
		$this->bootPublishes();
	}
	
	public function bootPublishes()
	{
		// Paths
		$srcPath =  __DIR__ . '/../..';
		$configPath = $srcPath.'/resources/config/folklore.php';
		$assetsPath = $srcPath.'/resources/assets';
		$viewsPath = $srcPath.'/resources/views';

		// Merge files
		$this->mergeConfigFrom($configPath, 'folklore');
		$this->loadViewsFrom($viewsPath, 'folklore');

		// Publishes
		$this->publishes([
			$configPath => config_path('folklore.php')
		], 'folklore.config');
		
		$this->publishes([
	        $viewsPath => base_path('resources/views/vendor/folklore'),
	    ], 'folklore.views');
		
		//Assets
		$this->publishes([
			$assetsPath => base_path('resources/assets')
		], 'folklore.assets');
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
		$this->registerDebugBar();
		$this->registerLocale();
		$this->registerConsoleCommands();
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
    	$loader->alias('Form', 'Orchestra\Support\Facades\Form');
	}
	
	public function registerDebugBar()
	{
		$this->app->register('Barryvdh\Debugbar\ServiceProvider');
		
		$loader = AliasLoader::getInstance();
    	$loader->alias('Debugbar', 'Barryvdh\Debugbar\Facade');
	}
	
	public function registerConsoleCommands()
	{
		$this->commands('Folklore\Laravel\Console\Commands\FolkloreInstallCommand');
		$this->commands('Folklore\Laravel\Console\Commands\FolkloreUpdateCommand');
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
