@extends('admin.layout')
@section('title','Forms & site copy')
@section('content')
<div class="cms-title"><div><p class="cms-kicker">DATABASE FORM DEFINITIONS</p><h1>Forms & options</h1><p>Edit service questions, options and labels. Keep field IDs unchanged for existing submissions.</p></div></div>
@foreach($forms as $form)<details class="cms-panel cms-section"><summary>{{ $form->name }}</summary><form method="post" action="/admin/forms/{{ $form->id }}">@csrf<label>Definition (JSON)<textarea name="definition" class="cms-code" rows="25">{{ json_encode($form->definition,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</textarea></label><button class="cms-button">Save definition</button></form></details>@endforeach
@endsection
