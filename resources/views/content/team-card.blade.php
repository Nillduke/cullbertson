<article class="people-card">
 <a class="people-card-link" href="/{{ $person->page->slug }}/">
  <div class="people-card-photo">
   @if($person->photo)<img src="{{ $person->photo }}" alt="{{ $person->name }}" width="600" height="600" loading="lazy">@else<div class="people-initials" aria-hidden="true">{{ collect(explode(' ',$person->name))->map(fn($part)=>mb_substr($part,0,1))->take(2)->join('') }}</div>@endif
   <span class="people-card-arrow" aria-hidden="true">&nearr;</span>
  </div>
  <div class="people-card-copy"><p class="people-role">{{ $person->role ?: $teamCopy['memberLabel'] }}</p><h3>{{ $person->name }}</h3><p class="people-excerpt">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($person->biography_html)))),125) }}</p><span class="people-profile-link">{{ $teamCopy['profileLink'] }} <span aria-hidden="true">&rarr;</span></span></div>
 </a>
</article>
