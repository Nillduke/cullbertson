@if($criticalTemplate ?? false)
    <link rel="preload" as="style" href="{{ $href }}" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ $href }}"></noscript>
@else
    <link rel="stylesheet" href="{{ $href }}">
@endif
