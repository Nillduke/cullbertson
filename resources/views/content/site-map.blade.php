@extends('layouts.app')
@section('content')
<main id="main" class="public-archive"><header class="archive-heading"><div class="archive-shell"><h1>{{ $mapCopy['heading'] }}</h1><div class="archive-intro">{{ $mapCopy['intro'] }}</div></div></header><div class="archive-shell public-site-map">
@foreach($pageGroups as $kind=>$items)<section><h2>{{ $mapCopy[$kind]??$mapCopy['pages'] }}</h2><ul>@foreach($items as $page)<li><a href="/{{ $page->slug }}{{ $page->slug?'/':'' }}">{{ $page->title }}</a></li>@endforeach</ul></section>@endforeach
@foreach(['categories'=>$categories,'tags'=>$tags,'authors'=>$authors] as $kind=>$items)<section><h2>{{ $mapCopy[$kind] }}</h2><ul>@foreach($items as $item)<li><a href="/{{ $item->path() }}/">{{ $item->name }}</a></li>@endforeach</ul></section>@endforeach
<section><h2>{{ $mapCopy['team'] }}</h2><a href="/agency-staff/">{{ $mapCopy['team'] }}</a></section><section><h2>{{ $mapCopy['xml'] }}</h2><a href="/wp-sitemap.xml">{{ $mapCopy['xml'] }}</a></section>
</div></main>
@endsection
