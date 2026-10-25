@extends('admin.layout')
@section('title','Media library')
@section('content')
<div class="cms-title"><div><p class="cms-kicker">YOUR WEBSITE IMAGES</p><h1>Media library</h1><p>Upload an image, then use its path in a page or post.</p></div></div>
<form class="cms-panel" method="post" action="/admin/media" enctype="multipart/form-data">@csrf<label>Image (JPEG, PNG or WebP, up to 5 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label><label>Image description<input name="alt" maxlength="255"></label><button class="cms-button">Upload image</button></form>
<div class="cms-media-grid">@foreach($media as $image)<article class="cms-panel"><img src="{{ $image->path }}" alt="{{ $image->alt }}"><strong>{{ $image->name }}</strong><label>Image path<input readonly value="{{ $image->path }}" aria-label="Image path for {{ $image->name }}"></label></article>@endforeach</div>{{ $media->links('pagination.cms') }}
@endsection
