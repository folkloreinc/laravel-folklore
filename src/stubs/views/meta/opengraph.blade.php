<!-- Open Graph -->
<meta property="og:locale" content="{{$locale}}">
<meta property="og:title" content="{{ $title }}" data-react-helmet="true">
<meta property="og:type" content="website" data-react-helmet="true">
<meta property="og:description" content="{{ $description }}" data-react-helmet="true">
<meta property="og:url" content="{{ $url }}" data-react-helmet="true">
@if(isset($image))
    <meta property="og:image" content="{{ $image }}" data-react-helmet="true">
@endif
