@extends('layouts.app')
@section('content')
<main id="main" class="auto-landing-main">
    @foreach($contentPage->sections as $section)
        {!! app(\App\Services\ContentRenderer::class)->render($section) !!}
    @endforeach
</main>
@include('partials.auto-landing-footer')
@endsection
