<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="{{ config('app.search_engine_indexing') ? 'index, follow' : 'noindex, nofollow' }}">
    <meta name="description" content="{{ $description ?? '' }}">
    <meta name="theme-color" content="#102c3e">
    <title>{{ $title ?? 'Culbertson Agency' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/culbertson-mark.svg') }}">
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ asset('assets/nunito-sans-roman.woff2') }}">
    <link rel="stylesheet" href="{{ asset('styles.css') }}?v={{ filemtime(public_path('styles.css')) }}">
    <link rel="stylesheet" href="{{ asset('exit-intent.css') }}?v={{ filemtime(public_path('exit-intent.css')) }}">
    @if(isset($contentPage) && $contentPage->type === 'blog_index')<link rel="stylesheet" href="{{ asset('cms.css') }}">@endif
    @if(!empty($insuranceArticles))
        <link rel="stylesheet" href="{{ asset('insurance-articles.css') }}?v={{ filemtime(public_path('insurance-articles.css')) }}">
        <script defer src="{{ asset('insurance-articles.js') }}?v={{ filemtime(public_path('insurance-articles.js')) }}"></script>
    @endif
    @foreach($styles ?? [] as $stylesheet)
        <link rel="stylesheet" href="{{ $stylesheet }}">
    @endforeach
    <script defer src="{{ asset('app.js') }}?v={{ filemtime(public_path('app.js')) }}"></script>
    <script defer src="{{ asset('exit-intent.js') }}?v={{ filemtime(public_path('exit-intent.js')) }}"></script>
    @foreach($scripts ?? [] as $script)
        <script defer src="{{ $script }}"></script>
    @endforeach
    @stack('head')
</head>
<body class="{{ $bodyClass ?? 'content-page' }}" data-service="{{ $service ?? '' }}">
    @if(isset($contentPage) && $contentPage->slug === '')
        <script type="application/json" id="cms-home-quote-products">{!! json_encode(\App\Models\FormDefinition::data('homepage-products'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    @endif
    <a class="skip-link" href="#main">Skip to content</a>
    {!! $siteBlocks['header'] ?? '' !!}
    @yield('content')
    @if(!empty($insuranceArticles))
        @include('partials.insurance-articles')
    @endif
    {!! $siteBlocks['footer'] ?? '' !!}
    {!! $siteBlocks['feedback'] ?? '' !!}
    {!! $siteBlocks['exit-intent'] ?? '' !!}
    @stack('scripts')
</body>
</html>
