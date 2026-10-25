<aside id="insurance-articles" class="insurance-articles" aria-labelledby="insurance-articles-title" data-article-slider>
    <div class="insurance-articles-shell">
        <div class="insurance-articles-heading">
            <p>{{ $siteCopy['articles']['eyebrow'] ?? '' }}</p>
            <h2 id="insurance-articles-title">{{ $articleFeedTitle ?? $siteCopy['articles']['title'] ?? '' }}</h2>
            <a href="{{ url('/expert-insurance-insights-and-tips-culbertson-agency-articles/') }}">{{ $siteCopy['articles']['viewAll'] ?? '' }} <span aria-hidden="true">↗</span></a>
        </div>
        <ul id="insurance-article-track" class="insurance-article-track" aria-label="Insurance articles" tabindex="0">
            @foreach($insuranceArticles as $article)
                <li class="insurance-article-card">
                    <a class="insurance-article-image" href="{{ url($article['url']) }}" tabindex="-1" aria-hidden="true"><img src="{{ asset(ltrim($article['image'], '/')) }}" alt="" width="600" height="540" loading="lazy"></a>
                    <div class="insurance-article-copy">
                        <p class="insurance-article-category">{{ in_array($articleCategory, $article['categories'], true) ? $articleCategory : $article['categories'][0] }}</p>
                        <h3><a href="{{ url($article['url']) }}">{{ $article['title'] }}</a></h3>
                        <a class="insurance-article-more" href="{{ url($article['url']) }}" aria-label="{{ $siteCopy['articles']['readMore'] ?? '' }}: {{ $article['title'] }}">{{ $siteCopy['articles']['readMore'] ?? '' }} <span aria-hidden="true">→</span></a>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="insurance-article-controls" hidden>
            <div class="insurance-article-meter" aria-hidden="true"><span></span></div>
            <div class="insurance-article-arrows">
                <button type="button" aria-label="Previous articles" aria-controls="insurance-article-track" data-article-previous><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 12H4m7-7-7 7 7 7"/></svg></button>
                <button type="button" aria-label="Next articles" aria-controls="insurance-article-track" data-article-next><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 12h16m-7-7 7 7-7 7"/></svg></button>
            </div>
            <p class="insurance-article-status" role="status" aria-live="polite"></p>
        </div>
    </div>
</aside>
