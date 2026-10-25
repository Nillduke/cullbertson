<div class="sp-insurance-grid" data-sp-list="insurance">
@foreach($items as $insurancePage)
@php($c=$insurancePage->insurance_content??[])
<article class="sp-insurance-card" data-sp-item="insurance"><a href="/{{ $insurancePage->slug }}/"><div class="sp-insurance-photo">@if($image=$c['hero']??$insurancePage->featured_image)<img src="{{ $image }}" alt="{{ $c['hero_alt']??'' }}" loading="lazy" width="720" height="460">@endif<span aria-hidden="true">&nearr;</span></div><div class="sp-insurance-card-copy"><h3>{{ $c['breadcrumb']??preg_replace('/\s*\|.*$/','',$insurancePage->title) }}</h3><p>{{ \Illuminate\Support\Str::limit(($c['intro_body']?:($insurancePage->description?:($c['subtitle']??''))),145) }}</p><span class="sp-text-link">{{ $copy['insuranceLink'] }} <b aria-hidden="true">&rarr;</b></span></div></a></article>
@endforeach
</div>
