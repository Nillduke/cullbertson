@extends('layouts.app')
@section('content')
<main id="main" class="quote-thanks-shell">
<section class="quote-thanks-card" aria-labelledby="quote-thanks-heading"><span class="quote-thanks-check" aria-hidden="true">&#10003;</span><p class="quote-thanks-eyebrow">{{ $copy['eyebrow'] }}</p><h1 id="quote-thanks-heading">{{ $copy['heading'] }}</h1><p class="quote-thanks-intro">{{ $copy['intro'] }}</p><div class="quote-thanks-reference"><span>{{ $copy['referenceLabel'] }}</span><strong>{{ $reference }}</strong></div><h2>{{ $copy['nextHeading'] }}</h2><ol class="quote-thanks-steps">@foreach($copy['steps'] as $step)<li><span>{{ $loop->iteration }}</span><div><h3>{{ $step['title'] }}</h3><p>{{ $step['body'] }}</p></div></li>@endforeach</ol><p class="quote-thanks-help">{{ $copy['help'] }} <a href="{{ $copy['phoneUrl'] }}">{{ $copy['phone'] }}</a></p><div class="quote-thanks-actions"><a class="quote-thanks-primary" href="{{ url('/') }}">{{ $copy['homeLabel'] }} &rarr;</a><a href="{{ url('/get-a-quote/') }}">{{ $copy['quoteLabel'] }}</a></div><p class="quote-thanks-notice">{{ $copy['coverageNotice'] }}</p></section>
</main>
@endsection
@if($runTracking)
@push('scripts')
<script id="quote-completion-data" type="application/json">{!! json_encode($completion,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push({!! json_encode($event,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!});</script>
{!! $goalScripts !!}
@endpush
@endif
