var url = require('url');
var servestatic = require('serve-static');
var proxy = require('proxy-middleware');
var fs = require('fs');


module.exports = function(grunt, options)
{
    var config = options.config;
    
    //Proxy the server
    var proxyOptions = url.parse('http://'+config.serverHost);
    proxyOptions.preserveHost = true;
    proxyOptions.via = 'browserSync';

    //Static
    var serveStatic = {
        'assets': servestatic(config.assetsPath),
        'tmp': servestatic(config.tmpPath)
    };
    
    return {
        server: {
            bsFiles: {
                src : [
                    '<%= config.assetsPath %>/js/{,*/}*.js',
                    '<%= config.assetsPath %>/js/app/{,*/}*.js',
                    '<%= config.tmpPath %>/css/*.css',
                    '<%= config.publicPath %>/img/{,*/}*.{gif,jpeg,jpg,png,svg,webp}',
                    '<%= config.appPath %>/**/*'
                ]
            },
            options: {
                watchTask: true,
                host: '<%= config.serverHost %>',
                open: 'external',
                server: {
                    baseDir: '<%= config.publicPath %>',
                    middleware: [
                        function(req,res,next) {
                            try
                            {
                                var path = url.parse(req.url).pathname;
                                if(path.match(/^\/bower_components\//))
                                {
                                    return serveStatic.assets(req,res,next);
                                }
                                else if(path.match(/^\/css\//) && fs.statSync(config.tmpPath+path).isFile())
                                {
                                    return serveStatic.tmp(req,res,next);
                                }
                                else if(path.match(/^\/js\//) && fs.statSync(config.assetsPath+path).isFile())
                                {
                                    return serveStatic.assets(req,res,next);
                                }
                            }
                            catch(e)
                            {
                                
                            }
                            
                            return next();
                            
                        },
                        proxy(proxyOptions)
                    ]
                }
            }
        }
    };
    
};
