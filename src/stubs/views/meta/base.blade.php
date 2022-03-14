<title>{{ $title }}</title>

<meta name="language" content="{{ $locale }}">
<meta name="description" content="{{ $description }}" data-react-helmet="true">

@if (isset($keywords) && sizeof($keywords))
<meta name="keywords" content="{{ $keywords->implode(',') }}" data-react-helmet="true">
@endif

<link rel="shortcut icon" href="{{ asset('static/media/favicon/favicon.ico') }}" type="image/x-ico" data-react-helmet="true">
<link rel="icon" href="{{ asset('static/media/favicon/favicon.png') }}" type="image/png" data-react-helmet="true">
