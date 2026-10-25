<section class="form-info{{ $compact ? ' form-info--compact' : '' }}" aria-label="{{ $legal['label'] }}">
    @if(!$compact)<div class="form-info-brand"><a href="{{ url('/') }}" aria-label="{{ $legal['agency'] }}"><img src="{{ asset($legal['logo']) }}" width="46" height="46" alt=""></a><div><strong>{{ $legal['agency'] }}</strong><span>{{ $legal['address'] }}</span><a href="{{ $legal['phoneUrl'] }}">{{ $legal['phone'] }}</a></div></div>@endif
    <p>{{ $legal['notice'] }}</p>
    @if(!$compact)<p>{{ $legal['quoteNotice'] }}</p>@endif
    <p class="form-info-powered">{{ $legal['powered'] }} <a href="{{ url('/') }}">{{ $legal['agency'] }}</a></p>
    <nav aria-label="{{ $legal['linksLabel'] }}">@foreach($legal['links'] as $link)<a href="{{ url($link['url']) }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>@endforeach</nav>
    @if(!$compact)<p class="form-info-small">{{ $legal['choicesNotice'] }}</p>@endif
</section>
