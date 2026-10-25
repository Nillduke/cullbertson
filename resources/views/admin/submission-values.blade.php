<dl class="sub-values">
@foreach($values as $key=>$value)
@php
if(is_array($value) && count($value)===1 && array_key_exists($key,$value)) $value=$value[$key];
@endphp
<div @if(is_array($value)) class="sub-value-group" @endif><dt>{{ is_int($key)?'Item '.($key+1):($labels[$key]??\Illuminate\Support\Str::headline($key)) }}</dt><dd>@if(is_array($value))@if($value)@include('admin.submission-values',['values'=>$value])@else Not provided @endif
@elseif(is_bool($value))<span class="sub-boolean {{ $value?'yes':'no' }}">{{ $value?'Yes':'No' }}</span>
@elseif($value===null || $value==='')<span class="sub-not-provided">Not provided</span>
@else {{ $value }} @endif</dd></div>
@endforeach
</dl>
