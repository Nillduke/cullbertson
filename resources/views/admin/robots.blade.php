@extends('admin.layout')
@section('title','Robots.txt')
@section('content')
<div class="cms-title"><div><p class="cms-kicker">SEARCH ENGINE SETTINGS</p><h1>Robots.txt</h1><p>Manage the crawl directives published at your website’s robots.txt URL.</p></div><a class="cms-button secondary" href="{{ url('/robots.txt') }}" target="_blank" rel="noopener">View live robots.txt ↗</a></div>
<div class="cms-panel cms-section"><p><strong>{{ $custom ? 'Custom directives are active.' : 'Using the environment default.' }}</strong> Saving replaces the complete robots.txt response immediately, including the environment’s default indexing rules. Keep your sitemap URL and any paths you want crawlers to avoid.</p><p>Robots.txt guides crawlers; it does not protect private pages or guarantee removal from search results.</p></div>
<form method="post" action="{{ url('/admin/robots') }}" class="cms-panel cms-section">@csrf
<label for="robots-content">Robots.txt content</label><textarea id="robots-content" name="content" class="cms-code" rows="20" required maxlength="100000" spellcheck="false" style="width:100%;font-family:monospace">{{ old('content',$content) }}</textarea>
<p><button class="cms-button" type="submit">Save &amp; publish robots.txt</button></p>
</form>
<details class="cms-panel cms-section"><summary>Environment default &amp; restore</summary><pre style="white-space:pre-wrap">{{ $defaults }}</pre><p>Restoring removes custom directives and uses the environment’s indexing setting again.</p><form method="post" action="{{ url('/admin/robots/reset') }}">@csrf<button class="cms-button secondary" type="submit">Restore environment default</button></form></details>
@endsection
