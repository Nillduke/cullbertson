@extends('layouts.app')
@section('content')
<main id="main" class="blog-listing">
    <h1 class="blog-listing-title">{{ $contentPage->title }}</h1>
    @if($posts->onFirstPage() && $leadPost) @include('content.blog-featured') @endif
    <section class="blog-listing-rest blog-listing-shell" aria-labelledby="more-articles-title"><h2 id="more-articles-title">{{ $listingCopy['latestTitle']??'' }}</h2>
    <div class="cms-blog-grid">
        @foreach($posts as $post)
            <article><a href="/{{ $post->slug }}/">@if($post->featured_image)<img src="{{ $post->featured_image }}" alt="" loading="lazy">@endif<h3>{{ $post->title }}</h3></a><div class="blog-listing-tags">@foreach($post->categoryLabels() as $category)<span>{{ $category }}</span>@endforeach</div><a class="blog-listing-more" href="/{{ $post->slug }}/" aria-label="{{ $listingCopy['readMore']??'' }}: {{ $post->title }}">{{ $listingCopy['readMore']??'' }} <span aria-hidden="true">&rarr;</span></a></article>
        @endforeach
    </div></section>
    <nav class="cms-pagination blog-listing-shell" aria-label="Article pages">{{ $posts->links('pagination.cms') }}</nav>
</main>
@endsection
