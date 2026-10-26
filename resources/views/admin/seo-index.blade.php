@extends('admin.layout')
@section('title','SEO & metadata')
@section('content')
<div class="cms-title"><div><p class="cms-kicker">SEARCH & SHARING</p><h1>SEO & metadata</h1><p>{{ $count }} URL profiles. Saved SEO fields take priority over the original rendered tags.</p></div><a class="cms-button" href="/admin/seo/create">Add URL profile</a></div>
@if(!config('app.search_engine_indexing'))<p class="cms-success">Preview protection is enabled. Public pages output noindex, nofollow. Enable SEARCH_ENGINE_INDEXING on the production server only when launching.</p>@endif
<form class="cms-panel cms-filter"><label>Search URLs and metadata<input name="q" value="{{ request('q') }}" placeholder="URL, meta title or description"></label><button class="cms-button">Search</button></form>
<div class="cms-panel cms-table-wrap"><table class="cms-table"><thead><tr><th>URL path</th><th>Meta title</th><th>Description</th><th></th></tr></thead><tbody>@forelse($profiles as $profile)<tr><td><a href="{{ $profile->url_path }}" target="_blank" rel="noopener">{{ $profile->url_path }}</a></td><td>{{ $profile->fields['title'] }}</td><td>{{ empty($profile->fields['description'])?'Not set in source':\Illuminate\Support\Str::limit($profile->fields['description'],100) }}</td><td><a href="/admin/seo/{{ $profile->id }}/edit">Edit SEO &rarr;</a></td></tr>@empty<tr><td colspan="4">No matching URL profiles.</td></tr>@endforelse</tbody></table>{{ $profiles->links('pagination.cms') }}</div>
@endsection
