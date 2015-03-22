module.exports = {
    dist: {
        options: {
            baseUrl: '<%= config.assetsPath %>/js',
            name: 'main',
            out: '<%= config.publicPath %>/js/main.build.js',
            mainConfigFile: '<%= config.assetsPath %>/js/main.js',
            paths: {
                requireLib: '../bower_components/requirejs/require'
            },
            include: 'requireLib',
            preserveLicenseComments: false,
            useStrict: true,
            wrap: true
        }
    }
};
