<?php namespace Folklore\Laravel\View\Composers;

use Illuminate\Contracts\View\View;

use Illuminate\Support\Str;
use App;
use Route;
use Asset;

class LayoutComposer {

    public function __construct()
    {
        
    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        //Head Assets
        $headContainer = Asset::container('head');
    	if(App::environment() == 'local')
        {
    		$headContainer->add('modernizr','bower_components/modernizr/modernizr.js');
    	}
        else
        {
    		$headContainer->add('modernizr','js/vendor/modernizr.js');
    	}
    	$headContainer->add('styles','css/main.css');

    	//Footer Assets
    	$footerContainer = Asset::container('footer');
    	if(App::environment() == 'local')
        {
    		$footerContainer->add('main','bower_components/requirejs/require.js',array(),array('data-main'=>'/js/main'));
    	}
        else
        {
    		$footerContainer->add('main','js/main.build.js');
    	}
        
        //Meta
        $view->with(array(
            'title' => trans('folklore::meta.title'),
            'description' => trans('folklore::meta.description')
        ));
        
        //Route informations
        $route = Route::current();
        $routeName = $route->getName();
        $routeClass = Str::slug($routeName);
        $view->with(array(
            'route' => $routeName,
            'routeClass' => $routeClass
        ));
    }

}
