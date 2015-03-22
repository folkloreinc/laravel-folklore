define(
[
	'jquery',
	'backbone',
	'backbone.marionette'

],

function(

	$,
	Backbone,
	Marionette

) {

	'use strict';
	
	var SiteController = Marionette.Controller.extend(
	{
        all: function()
        {
            
        }
    });


	return SiteController;

});
