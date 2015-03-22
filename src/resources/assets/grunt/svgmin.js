module.exports = {
    dist: {
        files: [{
            expand: true,
            cwd: '<%= config.publicPath %>/img',
            src: '{,*/}*.svg',
            dest: '<%= config.publicPath %>/img'
        }]
    }
};
