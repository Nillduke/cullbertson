@extends('layouts.app')
@section('content')
<main id="main" class="people-page">
 <header class="people-hero"><div class="people-shell people-hero-inner">
  <div class="people-hero-copy"><p class="people-eyebrow">{{ $archiveCopy['teamEyebrow'] }}</p><h1>{{ $teamCopy['headline'] }}</h1><p class="people-lead">{{ $teamCopy['intro'] }}</p><a class="people-button" href="#our-people">{{ $teamCopy['exploreLabel'] }} <span aria-hidden="true">&darr;</span></a></div>
  <div class="people-hero-art" aria-hidden="true"><span class="people-orbit"></span><div class="people-portrait-strips">@foreach($members->take(3) as $person)<div class="people-strip">@if($person->photo)<img src="{{ $person->photo }}" alt="" width="300" height="460" fetchpriority="{{ $loop->first ? 'high' : 'auto' }}">@endif</div>@endforeach</div><div class="people-location"><span></span>{{ $teamCopy['location'] }}</div></div>
 </div></header>
 <section class="people-shell people-directory" id="our-people" aria-labelledby="team-heading"><div class="people-section-heading"><div><p class="people-eyebrow">{{ $teamCopy['directoryLabel'] }}</p><h2 id="team-heading">{{ $teamCopy['directoryTitle'] }}</h2></div><p>{{ $teamCopy['directoryIntro'] }}</p></div><div class="people-grid">@foreach($members as $person)@include('content.team-card')@endforeach</div>@include('content.archive-pagination')</section>
 @include('content.team-cta')
</main>
@endsection
