define(
[
	'jquery',
	'backbone',
	'backbone.marionette',

	'modules/data',
	'modules/utils.text',
	'views/layout'

],

function(

	$,
	Backbone,
	Marionette,
	
	DataModule,
	UtilsTextModule,
	LayoutView

) {

	'use strict';
	
	var App = new Marionette.Application();
	
	//Modules
	App.module('data', DataModule);
	App.module('utils.text', UtilsTextModule);
	
	//Start
	App.on('start', function()
	{
		
		App.utils.text.start({
			fonts: ['Montserrat']
		});
	
		//Layout
		this.layout = new LayoutView({
			el: 'body'
		});
		this.layout.render();
		
		//Resize
		var globalChannel = Backbone.Wreqr.radio.channel('global');
		$(window).resize(function() {
			globalChannel.vent.trigger('window:resize', {
				width: $(window).width(),
				height: $(window).height()
			});
		});
		
		//History
		if (!Modernizr.history)
		{
			Backbone.history.start({
				pushState: true,
				hashChange: false
			});
		}
		else
		{
			Backbone.history.start({
				pushState: true
			});
		}
	});
	
	window.App = App;

	return App;

});
