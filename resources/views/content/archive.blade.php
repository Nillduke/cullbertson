@extends('layouts.app')
@section('content')
<main id="main" class="public-archive"><header class="archive-heading"><div class="archive-shell"><p>{{ $archiveCopy[$archiveKind.'Eyebrow']??'' }}</p><h1>{{ $archiveRecord->name }}</h1><div class="archive-intro">{{ $description }}</div></div></header>
<section class="archive-shell archive-grid" aria-label="{{ $archiveRecord->name }}">
@forelse($archivePosts as $post)<article class="archive-card"><a href="/{{ $post->slug }}/">@if($post->featured_image)<img src="{{ $post->featured_image }}" alt="" width="600" height="400" loading="lazy">@endif<h2>{{ $post->title }}</h2></a>@include('content.taxonomy-chips')@if($post->published_at)<time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('F j, Y') }}</time>@endif<a class="archive-more" href="/{{ $post->slug }}/">{{ $archiveCopy['readMore'] }} <span aria-hidden="true">→</span></a></article>
@empty<div class="archive-empty"><h2>{{ $archiveCopy['emptyTitle'] }}</h2><p>{{ $archiveCopy['emptyBody'] }}</p><a href="/expert-insurance-insights-and-tips-culbertson-agency-articles/">{{ $archiveCopy['browse'] }} →</a></div>@endforelse
</section>@include('content.archive-pagination')</main>
@endsection
