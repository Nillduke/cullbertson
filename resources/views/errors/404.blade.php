@extends('layouts.app')
@section('content')
{!! \App\Models\SiteBlock::where('key','not-found')->value('content_html') !!}
@endsection
