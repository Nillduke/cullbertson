<!DOCTYPE html>
<html lang="{{ $seo['lang'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($googleTracking=app(\App\Services\GoogleTracking::class)->configuration())
    @include('partials.google-tracking-head')
    @include('partials.seo-head')
    <meta name="theme-color" content="#102c3e">
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/culbertson-mark.svg') }}">
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ asset('assets/nunito-sans-roman.woff2') }}">
    @php($baseStylesheet = isset($contentPage) && $contentPage->slug === '' ? 'styles.home.min.css' : 'styles.shared.min.css')
    @php($criticalTemplate = isset($contentPage) && ($contentPage->slug === 'insurance' || $contentPage->type === 'post'))
    @if($criticalTemplate)
        <script>if('IntersectionObserver' in window)document.documentElement.classList.add('perf-defer-footer');</script>
        <style>html.perf-defer-footer .gradient-footer::before{background-image:linear-gradient(110deg,rgba(7,35,72,.94),rgba(20,84,139,.91) 48%,rgba(21,112,163,.92))}</style>
        <style>{!! file_get_contents(public_path('styles.header-critical.min.css')) !!}</style>
    @endif
    @include('partials.performance-stylesheet',['href'=>asset($baseStylesheet).'?v='.filemtime(public_path($baseStylesheet))])
    @include('partials.performance-stylesheet',['href'=>asset('exit-intent.css').'?v='.filemtime(public_path('exit-intent.css'))])
    <link rel="stylesheet" href="{{ asset('form-information.css') }}?v={{ filemtime(public_path('form-information.css')) }}">
    @if(isset($contentPage) && $contentPage->type === 'blog_index')<link rel="stylesheet" href="{{ asset('cms.css') }}">@endif
    @if(!empty($insuranceArticles))
        @include('partials.performance-stylesheet',['href'=>asset('insurance-articles.css').'?v='.filemtime(public_path('insurance-articles.css'))])
        <script defer src="{{ asset('insurance-articles.js') }}?v={{ filemtime(public_path('insurance-articles.js')) }}"></script>
    @endif
    @foreach($styles ?? [] as $stylesheet)
        @if($criticalTemplate && basename(parse_url($stylesheet, PHP_URL_PATH)) === 'support-pages.css')
            <style>{!! file_get_contents(public_path('styles.support-critical.min.css')) !!}</style>
        @elseif($criticalTemplate && basename(parse_url($stylesheet, PHP_URL_PATH)) === 'blog-detail.css')
            <style>{!! file_get_contents(public_path('blog-detail.css')) !!}</style>
        @endif
        @include('partials.performance-stylesheet',['href'=>$stylesheet])
    @endforeach
    <script defer src="{{ asset('app.js') }}?v={{ filemtime(public_path('app.js')) }}"></script>
    <script defer src="{{ asset('exit-intent.js') }}?v={{ filemtime(public_path('exit-intent.js')) }}"></script>
    @if(str_contains($bodyClass ?? '', 'quote-flow-page'))
        <link rel="stylesheet" href="/quote-progress.css?v={{ filemtime(public_path('quote-progress.css')) }}">
        <script defer src="/quote-progress.js?v={{ filemtime(public_path('quote-progress.js')) }}"></script>
    @endif
    @foreach($scripts ?? [] as $script)
        <script defer src="{{ $script }}{{ str_starts_with($script,'/quote-assets/') ? (str_contains($script,'?') ? '&' : '?').'rev='.filemtime(public_path(parse_url($script,PHP_URL_PATH))) : '' }}"></script>
    @endforeach
    @stack('head')
</head>
<body class="{{ $bodyClass ?? 'content-page' }}" data-service="{{ $service ?? '' }}">
    @include('partials.google-tracking-noscript')
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
