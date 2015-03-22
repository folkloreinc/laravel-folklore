module.exports = {
    options: {
        jshintrc: '.jshintrc',
        reporter: require('jshint-stylish')
    },
    all: [
        'Gruntfile.js',
        '<%= config.assetsPath %>/js/{,*/}*.js',
        '<%= config.assetsPath %>/js/app/{,*/}*.js',
        '!<%= config.assetsPath %>/js/vendor/*'
    ]
};
