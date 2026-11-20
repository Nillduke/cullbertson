<div class="cx-tools-page">
 @if($section==='actions')
 <div class="cx-page-heading"><span>YOUR FOLLOW-UP WORKSPACE</span><h1>Manual actions</h1><p>Find the conversations that need your team's attention.</p></div>
 <div class="cx-action-grid">
  @foreach([['drafts','Reply drafts','Continue an unsent customer reply.','/admin/inbox?view=drafts','mail'],['unassigned','Unassigned leads','Give each conversation an owner.','/admin/inbox?assigned_to=unassigned','team'],['partials','Partial quotes','Review saved details and follow up.','/admin/inbox?completion=incomplete','form'],['failed','Failed customer emails','Review the mail error and retry delivery.','/admin/mail#mail-history','shield']] as [$key,$title,$description,$link,$icon])
  <a class="cx-action-card" href="{{ url($link) }}"><span class="cx-action-icon">@include('admin.icon',['icon'=>$icon])</span><strong>{{ $metrics[$key] }}</strong><h2>{{ $title }}</h2><p>{{ $description }}</p><span class="cx-action-link">Open queue @include('admin.icon',['icon'=>'arrow'])</span></a>
  @endforeach
 </div>
 @elseif($section==='analytics')
 <div class="cx-page-heading"><span>INBOX PERFORMANCE</span><h1>Conversations at a glance</h1><p>Live counts from your saved leads and customer email delivery history. Dates use UTC.</p></div>
 <div class="cx-metric-grid">@foreach(['total'=>'Total submissions','today'=>'Received today','partials'=>'Partial quotes','completed'=>'Completed quotes','accepted'=>'Customer emails accepted','failed'=>'Customer emails failed'] as $key=>$label)<div><span>{{ $label }}</span><strong>{{ number_format($metrics[$key]) }}</strong></div>@endforeach</div>
 <section class="cx-report-card"><h2>Lead status</h2>@foreach($metrics['by_status'] as $status=>$count)<div class="cx-stat-row"><span>{{ ucfirst($status) }}</span><div><i style="width:{{ $metrics['total']?round($count/$metrics['total']*100,2):0 }}%"></i></div><strong>{{ number_format($count) }}</strong></div>@endforeach</section>
 @else
 <div class="cx-page-heading"><span>WORKSPACE CONNECTIONS</span><h1>Inbox settings</h1><p>Manage the services your conversations use.</p></div>
 <div class="cx-settings-grid"><section class="cx-report-card"><span class="cx-connection-state {{ $mailSettings?->enabled&&$mailSettings?->provider!=='log'?'ready':'' }}">{{ $mailSettings?->enabled&&$mailSettings?->provider!=='log'?'Outgoing email enabled':'Setup required' }}</span><h2>Email & SMTP</h2><p>Sender: <strong>{{ $mailSettings?->from_address?:'Not configured' }}</strong></p><p>Replies use your existing SMTP settings. Mail server acceptance does not confirm inbox delivery.</p><a class="cx-primary" href="{{ url('/admin/mail') }}">Manage email settings</a></section><section class="cx-report-card"><span class="cx-connection-state">SMS & VOICE</span><h2>Twilio</h2><p>Connect your Twilio account for two-way SMS, incoming call forwarding and agency callback calls from the inbox.</p><a class="cx-primary" href="{{ url('/admin/integrations/twilio') }}">Set up Twilio</a><p>Incoming email mailbox sync is separate and is not connected.</p></section></div>
 @endif
</div>
