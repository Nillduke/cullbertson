<div class="sp-team-grid" id="our-people">
@foreach($members as $person)<a class="sp-team-card" href="/{{ $person->page->slug }}/"><div><img src="{{ $person->photo }}" alt="{{ $person->name }}" loading="lazy" width="500" height="500"><span aria-hidden="true">&nearr;</span></div><p class="sp-eyebrow">{{ $person->role }}</p><h3>{{ $person->name }}</h3><span class="sp-text-link">{{ $copy['teamLink'] }} &rarr;</span></a>@endforeach
</div>
