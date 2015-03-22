module.exports = {
    dist: {
        files: [{
            expand: true,
            cwd: '<%= config.publicPath %>/img',
            src: '{,*/}*.{png,jpg,jpeg}',
            dest: '<%= config.publicPath %>/img'
        }]
    }
};
