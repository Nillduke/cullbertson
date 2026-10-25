@extends('layouts.app')
@section('content')
<main id="main" class="blog-detail" data-template="shared-blog-article">
<article aria-labelledby="article-title">
    <header class="blog-masthead">
        <div class="blog-detail-shell">
            <a class="blog-back" href="/expert-insurance-insights-and-tips-culbertson-agency-articles/"><span aria-hidden="true">←</span> {{ $blogCopy['back'] ?? '' }}</a>
            <p class="blog-eyebrow">{{ $articleCategory ?? $blogCopy['eyebrow'] ?? '' }}</p>
            <h1 id="article-title">{{ $contentPage->title }}</h1>
            <div class="blog-meta"><span class="blog-reading"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg>{{ $readingMinutes }} {{ $readingMinutes===1 ? ($blogCopy['readingSingular']??'') : ($blogCopy['readingPlural']??'') }}</span>@if($contentPage->published_at)<time datetime="{{ $contentPage->published_at->toDateString() }}">{{ $contentPage->published_at->format('F j, Y') }}</time>@endif @if($contentPage->author)<a class="blog-author" href="/{{ $contentPage->author->path() }}/">{{ $contentPage->author->name }}</a>@endif</div>
        </div>
    </header>
    @if($contentPage->featured_image)<figure class="blog-hero blog-detail-shell"><img src="{{ $contentPage->featured_image }}" alt="{{ $heroAlt }}" width="1920" height="1080" fetchpriority="high"></figure>@endif
    <div class="blog-prose">{!! $articleBody !!}</div>
    <div class="blog-prose blog-taxonomies">@include('content.taxonomy-chips',['post'=>$contentPage])</div>
    <div class="blog-end"><a href="/expert-insurance-insights-and-tips-culbertson-agency-articles/"><span aria-hidden="true">←</span> {{ $blogCopy['back'] ?? '' }}</a></div>
</article>
</main>
@endsection
