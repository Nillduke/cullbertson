@extends('layouts.app')
@section('content')
<main id="main" class="people-page people-profile">
 <header class="person-hero"><div class="people-shell"><a class="person-back" href="/agency-staff/"><span aria-hidden="true">&larr;</span> {{ $archiveCopy['backToTeam'] }}</a><div class="person-hero-grid">
  <div class="person-heading"><p class="people-eyebrow">{{ $teamCopy['profileEyebrow'] }}</p><h1>{{ $member->name }}</h1><p class="person-role">{{ $member->role ?: $teamCopy['memberLabel'] }}</p><div class="person-divider"></div><p class="person-location">{{ $teamCopy['location'] }}</p><a class="people-button" href="{{ $teamCopy['contactUrl'] }}">{{ $teamCopy['contactLabel'] }} <span aria-hidden="true">&rarr;</span></a></div>
  <div class="person-portrait">@if($member->photo)<img src="{{ $member->photo }}" alt="{{ $member->name }}" width="700" height="700" fetchpriority="high">@else<div class="people-initials">{{ collect(explode(' ',$member->name))->map(fn($part)=>mb_substr($part,0,1))->take(2)->join('') }}</div>@endif</div>
 </div></div></header>
 <article class="people-shell person-story"><div class="person-story-heading"><p class="people-eyebrow">{{ $teamCopy['bioEyebrow'] }}</p><h2>{{ $teamCopy['bioTitle'] }}<br><span>{{ explode(' ',$member->name)[0] }}.</span></h2></div><div class="person-bio">{!! $member->biography_html !!}</div></article>
 @if($otherMembers->isNotEmpty())<section class="people-related"><div class="people-shell"><div class="people-section-heading"><div><p class="people-eyebrow">{{ $teamCopy['directoryLabel'] }}</p><h2>{{ $teamCopy['otherTitle'] }}</h2><p>{{ $teamCopy['otherIntro'] }}</p></div><a class="people-text-link" href="/agency-staff/">{{ $teamCopy['allTeamLabel'] }} <span aria-hidden="true">&rarr;</span></a></div><div class="people-grid">@foreach($otherMembers as $person)@include('content.team-card')@endforeach</div></div></section>@endif
 @include('content.team-cta')
</main>
@endsection
