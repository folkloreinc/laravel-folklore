module.exports = {

    dist: {
        'devFile' : '<%= config.bowerPath %>/modernizr/modernizr.js',
        'outputFile' : '<%= config.publicPath %>/js/vendor/modernizr.js',
        'extra' : {
            'shiv' : true,
            'printshiv' : false,
            'load' : true,
            'mq' : false,
            'cssclasses' : false
        },
        'extensibility' : {
            'addtest' : false,
            'prefixed' : false,
            'teststyles' : false,
            'testprops' : false,
            'testallprops' : false,
            'hasevents' : false,
            'prefixes' : false,
            'domprefixes' : false
        },
        'uglify' : true,
        'tests' : [],
        'parseFiles' : false,
        'files' : {
            'src': [
                '<%= config.assetsPath %>/js/{,*/}*.js',
                '<%= config.assetsPath %>/js/app/{,*/}*.js',
                '<%= config.publicPath %>/css/{,*/}*.css',
                '!<%= config.publicPath %>/js/vendor/*'
            ]
        },
        'matchCommunityTests' : false,
        'customTests' : []
    }
};
