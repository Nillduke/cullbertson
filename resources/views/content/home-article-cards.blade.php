@foreach($articles as $article)
<article class="journal-card {{ $loop->index >= 4 ? 'article-extra' : '' }}" @if($loop->index >= 4) hidden @endif>
    <a class="journal-card-link" href="{{ url('/'.$article->slug) }}"><div class="journal-photo"><img src="{{ $article->featured_image }}" alt="{{ $article->title }}" width="660" height="750" loading="lazy"></div><div class="journal-copy"><h3><span>{{ $article->title }}</span><b aria-hidden="true">›</b></h3><span class="journal-accent" aria-hidden="true"></span><p>{{ $article->description }}</p></div></a>
</article>
@endforeach
