@php($insurance=old('insurance',$page->insurance_content ?? \App\Services\InsuranceFields::defaults()))
<div id="insurance-fields" @if(old('type',$page->type)!=='insurance') hidden @endif>
<h2>Shared insurance page design</h2><p>Every insurance page uses the same layout. Edit all copy, images and links here. Published changes appear immediately. Image paths come from the Media library. New page defaults can be edited under Forms & options.</p>
@foreach(\App\Services\InsuranceFields::groups() as $group=>$keys)
<details class="cms-panel" open><summary><strong>{{ $group }}</strong></summary><div class="cms-fields">
@foreach($keys as $key)<label class="wide">{{ ucfirst(str_replace('_',' ',$key)) }}
@if(str_contains($key,'body') || str_contains($key,'intro') || str_contains($key,'note'))<textarea rows="3" name="insurance[{{ $key }}]">{{ $insurance[$key] ?? '' }}</textarea>@else<input name="insurance[{{ $key }}]" value="{{ $insurance[$key] ?? '' }}">@endif
</label>@endforeach</div></details>@endforeach
<div class="cms-panel"><label>Feature bullet points (one per line)<textarea rows="4" name="feature_points">{{ old('feature_points',implode("\n",$insurance['feature_points']??[])) }}</textarea></label></div>
@foreach(['coverages'=>'Coverage options','questions'=>'Frequently asked questions'] as $key=>$label)
<div class="cms-panel" data-insurance-repeater="{{ $key }}"><h2>{{ $label }}</h2><p>Body accepts formatted HTML. Add, remove or reorder the rows; the shared design stays consistent.</p><div data-insurance-rows>
@foreach($insurance[$key]??[] as $index=>$row)<div class="cms-panel" data-insurance-row><label>Title<input name="insurance[{{ $key }}][{{ $index }}][title]" value="{{ $row['title'] }}"></label><label>Body HTML<textarea rows="5" data-rich-editor data-editor-auto="true" name="insurance[{{ $key }}][{{ $index }}][body]">{{ $row['body'] }}</textarea></label><button type="button" data-row-up>Move up</button> <button type="button" data-row-remove>Remove</button></div>@endforeach
</div><button type="button" data-row-add>Add {{ $key==='coverages'?'coverage option':'question' }}</button></div>
@endforeach
<div class="cms-panel"><label>Resource columns and links (JSON)<textarea class="cms-code" rows="20" name="resources_json">{{ old('resources_json',json_encode($insurance['resources']??[],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) }}</textarea></label><p>Each column has eyebrow, title, body and links (label + url). Add or remove columns here.</p></div>
</div>
@push('scripts')<script src="/cms-insurance.js" defer></script>@endpush
