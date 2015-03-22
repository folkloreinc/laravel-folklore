<!doctype html>
@include('folklore::partials.folklore')
<!--[if IE ]> <html class="ie" lang="{{ $locale }}"> <![endif]-->
<!--[if !(IE) ]><!--> <html lang="{{ $locale }}"> <!--<![endif]-->
<head>

	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

	<title>{{ $title }}</title>
	<meta name="description" content="{{ $description }}">

	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="shortcut icon" href="/favicon.ico" type="image/x-ico">
	<link rel="icon" href="/favicon.gif" type="image/gif">

	<!-- Open Graph meta -->
	<meta property="og:locale" content="{{ $locale }}_CA">
	@if (isset($thumbnail))
		<meta property="og:image" content="{{ $thumbnail }}">
	@endif
	<meta property="og:title" content="{{ $title }}">
	<meta property="og:type" content="website">
	<meta property="og:description" content="{{ $description }}">
	<meta property="og:url" content="{{ Request::url() }}">

	<!-- CSS -->
	{!! Asset::container('head')->styles() !!}

	<!-- Head Javascript -->
	<script type="text/javascript">
		var LANGUAGE = "{{ $locale }}";
		var WINDOW_LOADED = false;
	</script>
	{!! Asset::container('head')->scripts() !!}

</head>
<body class="{{ $routeClass }}" onload="WINDOW_LOADED = true;">

	<script>

		(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

		ga('create', '');
		ga('send', 'pageview');

	</script>

	<header id="header">


	</header>

	<section id="content">
		@yield('content')
	</section>

	<!-- Footer javascript -->
	{!! Asset::container('footer')->scripts() !!}

</body>
</html>
