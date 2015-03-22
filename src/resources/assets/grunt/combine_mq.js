module.exports = {
    dist: {
        expand: true,
        cwd: '<%= config.publicPath %>/css/',
        src: '*.css',
        dest: '<%= config.publicPath %>/css/'
    }
};
