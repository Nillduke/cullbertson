<div class="blog-listing-tags native-taxonomy-chips">
@foreach($post->categoryTerms as $term)<a href="/{{ $term->path() }}/" rel="category">{{ $term->name }}</a>@endforeach
@foreach($post->tags as $term)<a href="/{{ $term->path() }}/" rel="tag">{{ $term->name }}</a>@endforeach
</div>
