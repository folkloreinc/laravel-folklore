module.exports = {
    options: {
        browsers: ['last 5 versions', 'ie 8', 'ie 9']
    },
    dist: {
        expand: true,
        cwd: '<%= config.publicPath %>/css/',
        src: '*.css',
        dest: '<%= config.publicPath %>/css/'
    }
};
