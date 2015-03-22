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
	
	var DataModule = Marionette.Module.extend({

        onStart: function()
        {
            
        }
        
    });
    
    return DataModule;

});
