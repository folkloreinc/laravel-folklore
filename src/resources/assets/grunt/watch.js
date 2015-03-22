module.exports = {
    compass: {
        files: [
            '<%= config.assetsPath %>/scss/{,*/}*.{scss,sass}'
        ],
        tasks: ['compass:server']
    },
    gruntfile: {
        files: ['Gruntfile.js']
    },
    js: {
        files: [
            '<%= config.assetsPath %>/js/{,*/}*.js',
            '<%= config.assetsPath %>/js/app/{,*/}*.js',
            '!<%= config.assetsPath %>/js/vendor/*'
        ],
        tasks: ['jshint']
    }
};
