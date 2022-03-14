<!doctype html>
<!--[if IE ]> <html class="ie" lang="{{ $locale }}"> <![endif]-->
<!--[if !(IE) ]><!--> <html lang="{{ $locale }}"> <!--<![endif]-->
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @include('meta.all')

    @include('analytics.head')

    @if(!isset($inWebpack) || !$inWebpack)
        @include('assets.head')
    @endif
</head>
<body>
    @include('analytics.body')

	<div id="app"></div>
    <script type="application/json" id="app-props">@json($props)</script>

    @if(isset($inWebpack) && $inWebpack)
        <script type="text/javascript" src="/static/js/bundle.js"></script>
        <script type="text/javascript" src="/static/js/main.chunk.js"></script>
    @else
        @include('assets.body')
    @endif
</body>
</html>
