@extends('admin.layout')
@section('title','Edit sitemap entry')
@section('content')
<div class="cms-title"><div><p class="cms-kicker">XML SITEMAP</p><h1>{{ $entry['title'] }}</h1><p>{{ $entry['path'] }}</p></div><a class="cms-button secondary" href="{{ url('/admin/sitemap') }}">Back to sitemap</a></div>
<div class="cms-panel cms-section"><strong>Current status: {{ $entry['included']?'Included':$entry['reason'] }}</strong><p>These settings affect the XML sitemap only. They do not change page URLs, redirects, SEO metadata or whether a page can be opened.</p></div>
<form method="post" action="{{ url('/admin/sitemap') }}" class="cms-panel cms-section">@csrf<input type="hidden" name="path" value="{{ $entry['path'] }}"><input type="hidden" name="action" value="save">
<div class="cms-fields"><label>Sitemap inclusion<select name="visibility"><option value="auto" @selected(old('visibility',empty($entry['override']['excluded'])?'auto':'exclude')==='auto')>Include when eligible (automatic)</option><option value="exclude" @selected(old('visibility',empty($entry['override']['excluded'])?'auto':'exclude')==='exclude')>Exclude from sitemap</option></select></label>
<label>Change frequency<select name="frequency"><option value="">Automatic</option>@foreach($frequencies as $frequency)<option value="{{ $frequency }}" @selected(old('frequency',$entry['override']['frequency']??'')===$frequency)>{{ ucfirst($frequency) }}</option>@endforeach</select></label>
<label>Priority (0.0–1.0)<input type="number" name="priority" min="0" max="1" step="0.1" value="{{ old('priority',$entry['override']['priority']??'') }}" placeholder="Automatic: {{ $entry['priority'] }}"></label>
<label>Last modified override (UTC)<input type="datetime-local" name="modified" value="{{ old('modified',empty($entry['override']['modified'])?'':\Illuminate\Support\Carbon::parse($entry['override']['modified'])->format('Y-m-d\TH:i')) }}"></label></div>
<p>Leave optional fields blank to use automatic values. Last modified should reflect an actual content update; automatic mode uses the saved content timestamps.</p><button class="cms-button" type="submit">Save &amp; publish sitemap settings</button></form>
<form method="post" action="{{ url('/admin/sitemap') }}" class="cms-panel cms-section">@csrf<input type="hidden" name="path" value="{{ $entry['path'] }}"><input type="hidden" name="action" value="reset"><button class="cms-button secondary" type="submit">Restore automatic settings for this URL</button></form>
@endsection
