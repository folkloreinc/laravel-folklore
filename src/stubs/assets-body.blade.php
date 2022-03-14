@foreach ($entrypoints as $entrypoint)
    @if(preg_match('/\.js$/',  $entrypoint) === 1)
        @if(preg_match('/\/runtime-/',  $entrypoint) === 1)
            <script type="text/javascript">{!! file_get_contents(public_path($entrypoint)) !!}</script>
        @else
            <script type="text/javascript" src="/{{ $entrypoint }}"></script>
        @endif
    @endif
@endforeach