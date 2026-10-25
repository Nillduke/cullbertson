@extends('admin.layout')
@section('title','Site structure')
@section('content')
<div class="cms-title"><div><p class="cms-kicker">DATABASE CONTENT RELATIONSHIPS</p><h1>Site structure</h1><p>Categories, tags and authors generate public archives. Team records generate staff profiles. Redirects preserve old links.</p></div></div>
<nav class="cms-structure-nav">@foreach($kinds as $key=>$name)<a @if($key===$kind) aria-current="page" @endif href="/admin/structure?kind={{ $key }}">{{ $name }}</a>@endforeach</nav>
<details class="cms-panel cms-section"><summary>Add {{ strtolower($kinds[$kind]) }}</summary>@include('admin.structure-fields',['record'=>$newRecord])</details>
@foreach($records as $record)<details class="cms-panel cms-section"><summary>{{ $record->name??$record->slug??$record->parameter }} @if($kind==='query-redirects')= {{ $record->value }}@endif <span>@if(isset($record->pages_count)){{ $record->pages_count }} linked content records @elseif($kind==='team'){{ $record->page->status }} @else{{ $record->status }} &middot; {{ $record->destination }}@endif</span></summary>@include('admin.structure-fields')</details>@endforeach
{{ $records->links('pagination.cms') }}
@endsection
