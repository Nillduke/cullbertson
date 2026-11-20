<div class="cx-day"><span>{{ $item['created_at']->format('l, M j, Y') }}</span></div>
<div class="cx-event">@include('admin.icon',['icon'=>'form']) {{ ($item['payload']['origin']??'')==='twilio'?'Twilio conversation started':($item['completion_status']==='incomplete'?'Partial quote saved':'Website submission received') }} · {{ $item['reference'] }}</div>
<article class="cx-message inbound cx-submission"><span class="cx-avatar">{{ mb_strtoupper(mb_substr($item['display_name'],0,1)) }}</span><div><div class="cx-bubble"><div class="cx-message-label">{{ $item['display_name'] }} <span>{{ ($item['payload']['origin']??'')==='twilio'?'Twilio contact':'Website form' }}</span></div><h3>{{ $item['form_title'] }}</h3><p><strong>{{ $item['completion_status']==='incomplete'?'Partial quote - '.$item['progress_percent'].'% complete':'Submission received' }}</strong>@if($item['last_step_label']) - {{ $item['last_step_label'] }}@endif</p>
<dl class="cx-saved-contact"><div><dt>Name</dt><dd>{{ $item['name_provided']?$item['name']:($item['completion_status']==='incomplete'?'Not entered yet':'Not provided') }}</dd></div><div><dt>Email</dt><dd>{{ $item['email']?:'Not entered yet' }}</dd></div><div><dt>Phone</dt><dd>{{ $item['phone']?:'Not entered yet' }}</dd></div></dl>
@if($item['source_page'])<p class="cx-source-page">Page: <a href="{{ $item['source_page']['url'] }}" target="_blank" rel="noopener">{{ $item['source_page']['title']?:$item['source_page']['url'] }}</a></p>@endif
@if($item['message'])<div class="cx-message-body"><strong>Message</strong><br>{{ $item['message'] }}</div>@elseif($item['kind']==='quotes')<p>{{ $item['completion_status']==='incomplete'?'Only the answers saved so far are shown below. Fields not reached yet have not been submitted.':'All saved quote answers are shown below.' }}</p>@endif
@if($item['rating'])<p>Rating: {{ $item['rating'] }}/5</p>@endif
<details class="cx-all-answers"><summary>View saved form answers ({{ count($savedGroups) }} sections)</summary><div class="cx-saved-answers">@foreach($savedGroups as $group)<details open><summary>{{ $group['title'] }}</summary>@include('admin.submission-values',['values'=>$group['values']])</details>@endforeach</div></details>
<a class="cx-full-record" href="{{ url($item['url'].'/details') }}">View full submission record</a></div><time>{{ $item['created_at']->format('g:i A') }} UTC</time></div></article>
@forelse($messages as $message)
@php
 $provider=$message->twilioDelivery;
 $incoming=$provider?->direction==='inbound';
 $author=$incoming?$item['display_name']:($message->author?->name??'Team member');
 $statusLabel=$message->channel==='note'?'Internal note':match($message->status){'accepted'=>'Email accepted by SMTP','queued'=>'Email queued','sending'=>'Sending email','failed'=>'Email failed','blocked'=>'Email paused - Do not contact',default=>'Reply draft - Not sent'};
 if(in_array($message->channel,['sms','call']))$statusLabel=$message->status==='draft'?($message->channel==='sms'?'SMS draft - Not sent':'Call draft'):($message->channel==='sms'?'SMS':'Call').' - '.\Illuminate\Support\Str::headline(str_starts_with($message->status,'agent-')?'Agency callback '.substr($message->status,6):$message->status);
@endphp
<article class="cx-message {{ $message->channel==='note'?'note':($incoming?'inbound':'outbound') }} {{ $message->channel==='call'?'call':'' }}">
 <span class="cx-avatar {{ $incoming?'':'agent' }}">{{ mb_strtoupper(mb_substr($author,0,1)) }}</span>
 <div><div class="cx-bubble">
  <div class="cx-message-label">{{ $author }}<span>{{ $statusLabel }}</span></div>
  <div class="cx-message-body">{{ $message->body }}</div>
  @if($message->channel==='reply'&&$message->delivery)
   <div class="cx-email-result">To: {{ implode(', ',$message->delivery->recipients) }}
    @if($message->delivery->sent_at)
     <br>Accepted: {{ $message->delivery->sent_at->format('M j, g:i A') }} UTC
    @endif
    @if($message->delivery->last_error)
     <br>{{ $message->delivery->last_error }}
    @endif
    @if($message->status==='failed')
     <br><a href="{{ url('/admin/mail#mail-history') }}">View delivery history and retry</a>
    @endif
   </div>
  @endif
  @if($provider)
   <div class="cx-email-result">{{ $incoming?'From: '.$provider->from_number:'To: '.$provider->to_number }}
    @if($provider->duration!==null)
     &middot; {{ $provider->duration }} seconds
    @endif
    @if($provider->last_error)
     <br>{{ $provider->last_error }}
    @endif
   </div><details class="cx-provider-details"><summary>Twilio details</summary>{{ $provider->provider_sid?:'Provider reference pending' }}@if($provider->agent_status)<br>Agency leg: {{ $provider->agent_status }}@endif @if($provider->customer_status)<br>{{ $incoming?'Forwarded':'Customer' }} leg: {{ $provider->customer_status }}@endif</details>
  @endif
  @if(in_array($message->channel,['reply','sms'])&&$message->status==='draft')
   <button type="button" class="cx-edit-draft" data-edit-draft="{{ $message->id }}" data-channel="{{ $message->channel }}" data-body="{{ $message->body }}">Edit draft</button>
  @endif
 </div><time>{{ $message->created_at->format('M j, g:i A') }} UTC @if($message->updated_at->gt($message->created_at)) - Updated @endif</time></div>
</article>
@empty
<div class="cx-thread-start"><span>Start the conversation</span><p>Write an email or SMS reply, or add a private note for your team.</p></div>
@endforelse
