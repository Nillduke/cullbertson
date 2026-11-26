<!DOCTYPE html>
<html lang="{{ $seo['lang'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($globalScripts=app(\App\Services\GlobalScripts::class)->settings())
    @php($pageScripts=app(\App\Services\PageScripts::class)->forRequest(request(),$runTracking??false))
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
    <script defer src="{{ asset('form-session.js') }}?v={{ filemtime(public_path('form-session.js')) }}"></script>
    <script defer src="{{ asset('app.js') }}?v={{ filemtime(public_path('app.js')) }}"></script>
    <script defer src="{{ asset('exit-intent.js') }}?v={{ filemtime(public_path('exit-intent.js')) }}"></script>
    @if(str_contains($bodyClass ?? '', 'quote-flow-page'))
        <link rel="stylesheet" href="/quote-progress.css?v={{ filemtime(public_path('quote-progress.css')) }}">
        <script defer src="/quote-progress.js?v={{ filemtime(public_path('quote-progress.js')) }}"></script>
    @endif
    @foreach($scripts ?? [] as $script)
        <script defer src="{{ $script }}{{ str_starts_with($script,'/quote-assets/') ? (str_contains($script,'?') ? '&' : '?').'rev='.filemtime(public_path(parse_url($script,PHP_URL_PATH))) : '' }}"></script>
    @endforeach
    @if(str_contains($bodyClass ?? '', 'auto-landing-page'))<script defer src="/auto-landing.js?v={{ filemtime(public_path('auto-landing.js')) }}"></script>@endif
    {!! $globalScripts['header'] !!}
    {!! $pageScripts['header'] !!}
    @stack('head')
    @if(!empty($globalScripts['css_enabled']) && $globalScripts['css']!=='')<style id="global-custom-css">{!! $globalScripts['css'] !!}</style>@endif
    @if($pageScripts['css']!=='')<style id="page-custom-css">{!! $pageScripts['css'] !!}</style>@endif
</head>
<body class="{{ $bodyClass ?? 'content-page' }}" data-service="{{ $service ?? '' }}">
    @include('partials.google-tracking-noscript')
    @if(isset($contentPage) && $contentPage->slug === '')
        <script type="application/json" id="cms-home-quote-products">{!! json_encode(\App\Models\FormDefinition::data('homepage-products'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    @endif
    <a class="skip-link" href="#main">Skip to content</a>
    @if(str_contains($bodyClass ?? '', 'auto-landing-page'))
        <header class="auto-landing-header"><a href="/" aria-label="Culbertson Agency home"><img src="/assets/culbertson-logo.svg" alt="Culbertson Agency"></a><div><a class="auto-language" href="/">Español</a><a class="auto-call" href="tel:+12392107700"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7l.5 2.8a2 2 0 0 1-.6 1.9L7.1 10.3a16 16 0 0 0 6.6 6.6l1.9-1.9a2 2 0 0 1 1.9-.6l2.8.5a2 2 0 0 1 1.7 2Z"/></svg> Call</a></div></header>
    @else
        {!! $siteBlocks['header'] ?? '' !!}
    @endif
    @yield('content')
    @if(!empty($insuranceArticles))
        @include('partials.insurance-articles')
    @endif
    @if(!str_contains($bodyClass ?? '', 'auto-landing-page')){!! $siteBlocks['footer'] ?? '' !!}@endif
    {!! $siteBlocks['feedback'] ?? '' !!}
    {!! $siteBlocks['exit-intent'] ?? '' !!}
    {!! $globalScripts['footer'] !!}
    {!! $pageScripts['footer'] !!}
    @stack('scripts')
</body>
</html>
