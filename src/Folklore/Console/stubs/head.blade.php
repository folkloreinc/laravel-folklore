@foreach ($entrypoints as $entrypoint)
    @if(preg_match('/\/runtime-/',  $entrypoint) === 0 && preg_match('/\.js/',  $entrypoint) === 1)
        <link href="/{{ $entrypoint }}" ref="preload" as="script" />
    @endif
    @if(preg_match('/\.css$/',  $entrypoint) === 1)
        <link href="/{{ $entrypoint }}" rel="stylesheet" type="text/css" />
    @endif
@endforeach