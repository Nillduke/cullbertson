<section class="blog-featured blog-listing-shell" aria-labelledby="featured-articles-title">
    <article class="blog-lead">
        <a class="blog-lead-image" href="/{{ $leadPost->slug }}/" tabindex="-1" aria-hidden="true">@if($leadPost->featured_image)<img src="{{ $leadPost->featured_image }}" alt="" width="1200" height="675" fetchpriority="high">@endif</a>
        <div class="blog-lead-copy">
            <div class="blog-listing-tags">@foreach($leadPost->categoryLabels() as $category)<span>{{ $category }}</span>@endforeach<span>{{ $listingCopy['articleTag']??'' }}</span></div>
            <h2><a href="/{{ $leadPost->slug }}/">{{ $leadPost->title }}</a></h2>
            <p class="blog-lead-excerpt">{{ $leadExcerpt }}</p>
            @if($leadPost->published_at)<time datetime="{{ $leadPost->published_at->toDateString() }}">{{ $leadPost->published_at->format('F j, Y') }}</time>@endif
            <a class="blog-listing-more" href="/{{ $leadPost->slug }}/" aria-label="{{ $listingCopy['readMore']??'' }}: {{ $leadPost->title }}">{{ $listingCopy['readMore']??'' }} <span aria-hidden="true">→</span></a>
        </div>
    </article>
    <aside class="blog-featured-sidebar"><h2 id="featured-articles-title">{{ $listingCopy['featuredTitle']??'' }}</h2>
        @foreach($featuredPosts->skip(1) as $post)
        <article class="blog-featured-small"><div class="blog-featured-small-copy">
            <div class="blog-listing-tags">@foreach($post->categoryLabels() as $category)<span>{{ $category }}</span>@endforeach<span>{{ $listingCopy['articleTag']??'' }}</span></div>
            <h3><a href="/{{ $post->slug }}/">{{ $post->title }}</a></h3>
            <a class="blog-listing-more" href="/{{ $post->slug }}/" aria-label="{{ $listingCopy['readMore']??'' }}: {{ $post->title }}">{{ $listingCopy['readMore']??'' }} <span aria-hidden="true">→</span></a>
        </div>@if($post->featured_image)<a class="blog-featured-thumb" href="/{{ $post->slug }}/" tabindex="-1" aria-hidden="true"><img src="{{ $post->featured_image }}" alt="" width="160" height="160" loading="lazy"></a>@endif</article>
        @endforeach
    </aside>
</section>
