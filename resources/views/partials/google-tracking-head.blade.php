@if($googleTracking)
    <script id="culbertson-tracking-config" type="application/json">{!! json_encode($googleTracking,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    <link rel="stylesheet" href="{{ asset('tracking.css') }}?v={{ filemtime(public_path('tracking.css')) }}">
    <script defer src="{{ asset('tracking.js') }}?v={{ filemtime(public_path('tracking.js')) }}"></script>
@endif
