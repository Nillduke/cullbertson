<section class="form-info{{ $compact ? ' form-info--compact' : '' }}" aria-label="{{ $legal['label'] }}">
    <div class="form-info-brand"><a href="{{ url('/') }}" aria-label="{{ $legal['agency'] }}"><img src="{{ asset($legal['logo']) }}" width="46" height="46" alt=""></a><div><strong>{{ $legal['agency'] }}</strong><span>{{ $legal['address'] }}</span><a href="{{ $legal['phoneUrl'] }}">{{ $legal['phone'] }}</a></div></div>
    <p class="form-info-consent">{{ $legal['notice'] }}@if(!empty($legal['noticeLinks'])) @foreach($legal['noticeLinks'] as $link)@if(!$loop->first) {{ $legal['noticeJoin'] ?? 'and' }} @endif<a href="{{ url($link['url']) }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>@endforeach.@endif</p>
    <p class="form-info-sms">{{ $legal['quoteNotice'] }}@if(!empty($legal['smsLink'])) <a href="{{ url($legal['smsLink']['url']) }}" target="_blank" rel="noopener">{{ $legal['smsLink']['label'] }}</a> {{ $legal['smsSuffix'] ?? '' }}@endif</p>
    <p class="form-info-powered">{{ $legal['powered'] }} <a href="{{ url('/') }}">{{ $legal['agency'] }}</a></p>
    <nav aria-label="{{ $legal['linksLabel'] }}">@foreach($legal['links'] as $link)<a href="{{ url($link['url']) }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>@endforeach</nav>
    <p class="form-info-small">{{ $legal['choicesNotice'] }}</p>
</section>
