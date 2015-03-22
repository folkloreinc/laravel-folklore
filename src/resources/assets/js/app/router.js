define(
[
	'jquery',
	'backbone',
	'backbone.marionette',
    
    'controllers/site'
],

function(

	$,
	Backbone,
	Marionette,
    
    SiteController

) {

	'use strict';
	
	var Router = Marionette.AppRouter.extend({
        
        controller: new SiteController(),
        
        appRoutes: {
            '*path': 'all'
        }

    });


	return Router;

});
