define(
[
	'jquery',
	'underscore',
	'backbone',
	'backbone.marionette'
],

function(

	$,
	_,
	Backbone,
	Marionette

) {
	'use strict';
	
	var LayoutView = Marionette.LayoutView.extend({
		
        template: false,
		
        regions:
		{
            
        },
		
		initialize: function()
		{
			this.channel = Backbone.Wreqr.radio.channel('global');
			this.listenTo(this.channel.vent, 'resize', this.onResize);
		},
		
		onRender: function()
		{
			
		},
		
		onResize: function()
		{
			
		}
    });

	return LayoutView;

});
