<title>{{ $seo['title'] }}</title>
<meta name="robots" content="{{ $seo['robots'] }}">
@if(($seo['description']??'')!=='')<meta name="description" content="{{ $seo['description'] }}">@endif
<link rel="canonical" href="{{ $seo['canonical'] }}">
@foreach(['og:title','og:description','og:image','og:type','og:url','og:site_name'] as $key)
@if(!empty($seo[$key]))<meta property="{{ $key }}" content="{{ $seo[$key] }}">@endif
@endforeach
@foreach(['twitter:card','twitter:title','twitter:description','twitter:image'] as $key)
@if(!empty($seo[$key]))<meta name="{{ $key }}" content="{{ $seo[$key] }}">@endif
@endforeach
@foreach($seo['hreflang'] as $alternate)<link rel="alternate" hreflang="{{ $alternate['lang'] }}" href="{{ $alternate['url'] }}">@endforeach
@foreach($seo['schemas'] as $schema)<script type="application/ld+json">{!! json_encode($schema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endforeach
