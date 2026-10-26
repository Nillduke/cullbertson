<section class="sub-card"><header><h2>Original WordPress submission</h2></header><div class="sub-card-body">
<p><strong>{{ $legacy['form_name'] }}</strong><br>{{ $legacy['source'] }} &middot; Original entry #{{ $legacy['entry_id'] }} &middot; Original status: {{ $legacy['original_status'] }}</p>
@include('admin.submission-values',['values'=>$legacy['fields']])
@if(!empty($legacy['notes']))<details><summary>Original entry notes ({{ count($legacy['notes']) }})</summary>@include('admin.submission-values',['values'=>$legacy['notes']])</details>@endif
<details><summary>Original record and field metadata</summary>@include('admin.submission-values',['values'=>array_intersect_key($legacy,array_flip(['entry','meta','values']))])</details>
<p class="sub-sidebar-help">Imported historical record. No notification email was sent for this import.</p>
</div></section>
