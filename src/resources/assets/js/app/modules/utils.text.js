define(
[
	'jquery',
	'underscore',
	'backbone',
	'backbone.marionette',
	
	'fontloader'
],

function(
    $,
	_,
	Backbone,
	Marionette,
	
	FontLoader
) {

	'use strict';
	
	var UtilsTextModule = Marionette.Module.extend({
		
		startWithParent: false,
		
		_fontsLoading: null,
		_fontsLoaded: null,
		
		onStart: function(options)
		{
			if(options.fonts && options.fonts.length)
			{
				this._fontsLoading = options.fonts;
				this._fontsLoaded = null;
				var fontLoader = new FontLoader(options.fonts, {
					'fontsLoaded': _.bind(function(error)
					{
						this._fontsLoading = null;
						this._fontsLoaded = options.fonts;
						this.app.channel.vent.trigger('utils:text:fontsLoaded');
					},this)
				});
				fontLoader.loadFonts();
			}
		},
		
		isFontLoaded: function(fontFamily)
		{
			if(!this._fontsLoaded)
			{
				return false;
			}
			return _.indexOf(this._fontsLoaded, fontFamily) !== -1 ? true:false;
		},
		
		fontsLoaded: function(cb)
		{
			if(!this._fontsLoaded)
			{
				this.listenToOnce(this.app.channel.vent, 'utils:text:fontsLoaded', cb);
			}
			else
			{
				window.setTimeout(cb,1);
			}
		},
		
		getElementHeights: function($el, cb, opts)
	    {
	        
	        var options = _.extend({
	            fontFamily: $el.css('fontFamily'),
	            minWidth: 0,
	            maxWidth: window.innerWidth,
				steps: 20,
				waitForFontsToLoad: false
	        }, opts);
			
			var fontFamilies = options.fontFamily.split(',');
			var allFontsLoaded = true;
			for(var i = 0, fl = fontFamilies.length; i < fl; i++)
			{
				fontFamilies[i] = $.trim(fontFamilies[i]);
				if(!this.isFontLoaded(fontFamilies[i]))
				{
					allFontsLoaded = false;
				}
			}
			
			if(!allFontsLoaded)
			{
				var fontLoader = new FontLoader(fontFamilies, {
		            fontsLoaded: _.bind(function(error)
		            {
						var heights = this._getElementHeights($el, options);
						cb(heights);
		            },this)
		        });
		        fontLoader.loadFonts();
			}
			else
			{
				var heights = this._getElementHeights($el, options);
				cb(heights);
			}
	        
	    },
		
		_getElementHeights: function($el, options)
		{
			var minWidth = options.minWidth;
			var maxWidth = options.maxWidth;
			var steps = options.steps;
			
			var sizes = [];
				
			var lastHeight = 0;
			var startWidth = 0;
			var lastWidth = 0;
			var height = 0;
			var lastAddedHeight = 0;
			
			for(var width = maxWidth; width > minWidth; width -= steps)
			{
				$el.css('width',width);
				height = $el.outerHeight();
				if(lastHeight === 0)
				{
					startWidth = width;
					lastHeight = height;
				}
				else if(lastHeight !== height)
				{
					sizes.push({
						maxWidth: startWidth,
						minWidth: lastWidth,
						height: lastHeight
					});
					lastAddedHeight = lastHeight;
					startWidth = lastWidth;
					lastHeight = height;
				}
				lastWidth = width;
			}
			
			if(lastHeight !== lastAddedHeight)
			{
				sizes.push({
					maxWidth: startWidth,
					minWidth: lastWidth,
					height: lastHeight
				});
			}
			
			return sizes;
		}
        
    });
    
    return UtilsTextModule;

});
