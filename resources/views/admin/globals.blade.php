@extends('admin.layout')
@section('title','Shared content')
@section('content')
<div class="cms-title"><div><p class="cms-kicker">SHARED ACROSS YOUR WEBSITE</p><h1>Shared content</h1><p>Update header menus, footer, feedback drawer and exit popup here.</p></div></div>
@foreach($blocks as $block)<details class="cms-panel cms-section"><summary>{{ $block->name }}</summary><form method="post" action="/admin/blocks/{{ $block->id }}">@csrf<label>Content HTML<textarea name="content_html" class="cms-code" data-rich-editor data-editor-auto="false" rows="20">{{ $block->content_html }}</textarea></label><button class="cms-button">Save {{ $block->name }}</button></form></details>@endforeach
@endsection
