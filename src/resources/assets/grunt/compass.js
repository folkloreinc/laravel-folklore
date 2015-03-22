module.exports = {
    options: {
        sassDir: '<%= config.assetsPath %>/scss',
        imagesDir: '<%= config.publicPath %>/img',
        javascriptsDir: '<%= config.publicPath %>/js',
        fontsDir: '<%= config.publicPath %>/css/fonts',
        importPath: '<%= config.bowerPath %>',
        httpImagesPath: '/img',
        httpGeneratedImagesPath: '/img',
        httpFontsPath: '/css/fonts',
        relativeAssets: false
    },
    dist: {
        options: {
            cssDir: '<%= config.publicPath %>/css',
            generatedImagesDir: '<%= config.publicPath %>/img',
            debugInfo: false,
            force: true
        }
    },
    server: {
        options: {
            cssDir: '<%= config.tmpPath %>/css',
            generatedImagesDir: '<%= config.tmpPath %>/img',
            debugInfo: true
        }
    }
};
