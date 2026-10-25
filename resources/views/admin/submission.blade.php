@extends('admin.layout')
@section('title','Submission details')
@section('content')
<link rel="stylesheet" href="/admin-submissions.css?v={{ filemtime(public_path('admin-submissions.css')) }}">
<div class="sub-detail-nav"><a href="/admin/inbox?kind={{ $item['kind'] }}">&larr; Back to {{ ['quotes'=>'quote requests','contacts'=>'contact enquiries','feedback'=>'feedback'][$item['kind']] }}</a><div>@if($previous)<a href="/admin/inbox/{{ $item['kind'] }}/{{ $previous }}">&larr; Newer</a>@endif @if($next)<a href="/admin/inbox/{{ $item['kind'] }}/{{ $next }}">Older &rarr;</a>@endif</div></div>
<div class="sub-detail-heading"><span class="sub-avatar large {{ $item['kind'] }}">{{ mb_strtoupper(mb_substr($item['name'],0,1)) }}</span><div><p class="cms-kicker">{{ ['quotes'=>'QUOTE REQUEST','contacts'=>'CONTACT ENQUIRY','feedback'=>'WEBSITE FEEDBACK'][$item['kind']] }}</p><h1>{{ $item['name'] }}</h1><p>{{ $item['subject'] }} <span>&middot; {{ $item['reference'] }}</span></p></div><span class="sub-status {{ $item['status'] }}"><i></i>{{ ucfirst($item['status']) }}</span></div>
<div class="sub-detail-grid"><div class="sub-main-column">
@if($item['message'])
<section class="sub-card"><header>@include('admin.icon',['icon'=>'inbox'])<h2>{{ $item['kind']==='feedback'?'Visitor feedback':'Customer message' }}</h2>@if($item['rating'])<span class="sub-rating">{{ $item['rating'] }}/5 <span aria-hidden="true">&#9733;</span></span>@endif</header><div class="sub-message">{{ $item['message'] }}</div></section>
@endif
@if($item['kind']==='quotes')
<div class="sub-section-label"><h2>Submitted form answers</h2><span>As provided by the customer</span></div>
@foreach($groups as $group)
<section class="sub-card sub-answer-card"><header><span class="sub-group-number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><h2>{{ $group['title'] }}</h2></header><div class="sub-card-body">@include('admin.submission-values',['values'=>$group['values']])</div></section>
@endforeach
@else
<section class="sub-card"><header>@include('admin.icon',['icon'=>'form'])<h2>Submission details</h2></header><div class="sub-card-body">@include('admin.submission-values',['values'=>array_diff_key($item['payload'],array_flip(['message','id','createdAt']))])</div></section>
@endif
</div><aside class="sub-detail-sidebar"><section class="sub-card"><header><h2>Manage submission</h2>@include('admin.icon',['icon'=>'settings'])</header><div class="sub-card-body"><p class="sub-sidebar-help">Keep your inbox up to date as you follow up.</p><form method="post" action="{{ $item['url'] }}/status">@csrf<label>Status<select name="status">@foreach($statuses as $status)<option value="{{ $status }}" @selected($item['status']===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label><button class="cms-button">Update status</button></form></div></section>
<section class="sub-card"><header><h2>Contact information</h2>@include('admin.icon',['icon'=>'team'])</header><div class="sub-contact"><div><small>Customer</small><strong>{{ $item['name'] }}</strong></div><div><small>Email address</small>@if(filter_var($item['email'],FILTER_VALIDATE_EMAIL))<a href="mailto:{{ $item['email'] }}">{{ $item['email'] }}</a>@else<span>{{ $item['email']?:'Not provided' }}</span>@endif</div><div><small>Phone number</small>@if(preg_match('/\d{7}/',preg_replace('/\D/','',$item['phone'])))<a href="tel:{{ preg_replace('/[^+0-9]/','',$item['phone']) }}">{{ $item['phone'] }}</a>@else<span>{{ $item['phone']?:'Not provided' }}</span>@endif</div></div></section>
<section class="sub-card"><header><h2>Submission record</h2>@include('admin.icon',['icon'=>'shield'])</header><dl class="sub-record"><div><dt>Reference</dt><dd>{{ $item['reference'] }}</dd></div><div><dt>Received</dt><dd>{{ $item['created_at']->format('M j, Y') }}<small>{{ $item['created_at']->format('H:i') }} UTC</small></dd></div><div><dt>Last updated</dt><dd>{{ $item['updated_at']->diffForHumans() }}</dd></div><div><dt>Source</dt><dd>Website form</dd></div></dl></section><p class="sub-privacy-note">@include('admin.icon',['icon'=>'shield']) Visible only to authorized administrators.</p></aside></div>
@endsection
