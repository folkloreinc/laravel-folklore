require.config({
    
    baseUrl: '/js',

    paths: {
        
        'jquery': '../bower_components/jquery/jquery.min',
        'text': '../bower_components/requirejs-text/text',
        'underscore': '../bower_components/underscore/underscore',
        'backbone': '../bower_components/backbone/backbone',
        'backbone.marionette': '../bower_components/backbone.marionette/lib/backbone.marionette',
        
        'fontloader': 'vendor/fontloader',
        
        'controllers' : 'app/controllers',
        'data' : 'app/data',
        'views' : 'app/views',
        'models' : 'app/models',
        'collections' : 'app/collections',
        'modules' : 'app/modules',
        'templates' : 'app/templates'

    },
    shim: {

        'underscore': {exports: '_'},
        'backbone': {exports: 'Backbone',deps: ['jquery','underscore']}

    }
});

require(
[
	'jquery','underscore','backbone',

    'app/app'

], function ($,_,Backbone,App) {

    'use strict';
    
    $(function() {
        App.start();
    });
});
