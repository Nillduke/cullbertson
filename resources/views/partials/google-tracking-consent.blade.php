@if($googleTracking)
<section class="tracking-choice" id="tracking-choice" aria-labelledby="tracking-choice-title" aria-describedby="tracking-choice-description" hidden>
    <div><h2 id="tracking-choice-title" tabindex="-1">{{ $googleTracking['copy']['title'] }}</h2><p id="tracking-choice-description">{{ $googleTracking['copy']['description'] }}</p><a href="{{ url($googleTracking['copy']['policyUrl']) }}" target="_blank" rel="noopener">{{ $googleTracking['copy']['policyLabel'] }}</a><p class="tracking-choice-note">{{ $googleTracking['copy']['note'] }}</p></div>
    <div class="tracking-choice-actions"><button type="button" data-tracking-choice="allow">{{ $googleTracking['copy']['allow'] }}</button><button type="button" data-tracking-choice="deny">{{ $googleTracking['copy']['deny'] }}</button><button type="button" class="tracking-choice-close" data-tracking-close hidden>{{ $googleTracking['copy']['close'] }}</button></div>
</section>
<button type="button" class="tracking-settings" id="tracking-settings" aria-controls="tracking-choice" aria-expanded="false" hidden>{{ $googleTracking['copy']['settings'] }}</button>
@endif
