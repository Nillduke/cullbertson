<aside class="cx-list-panel" aria-label="Team inbox">
 <div class="cx-list-title"><h2>Team inbox <span>{{ $entries->total() }}</span></h2><a class="cx-icon-button" href="{{ url('/admin/inbox').'?'.http_build_query($filters) }}" title="Refresh inbox" aria-label="Refresh inbox">@include('admin.icon',['icon'=>'refresh'])</a></div>
 <nav class="cx-tabs" aria-label="Inbox views">
  @foreach(['unread'=>['Unread','mail'],'all'=>['All','inbox'],'recent'=>['Recent','clock'],'starred'=>['Starred','star']] as $view=>[$label,$icon])
  <a href="{{ url('/admin/inbox').'?'.http_build_query(array_replace($filters,['view'=>$view])) }}" @if(($filters['view']??'all')===$view) aria-current="page" @endif><span>@include('admin.icon',['icon'=>$icon])@if($view==='unread'&&$unreadCount)<b>{{ $unreadCount }}</b>@endif</span>{{ $label }}</a>
  @endforeach
 </nav>
 <form method="get" action="{{ url('/admin/inbox') }}" class="cx-search-form" id="cx-search-form">
  <div class="cx-search"><span>@include('admin.icon',['icon'=>'search'])</span><input name="q" value="{{ $filters['q']??'' }}" aria-label="Search conversations" placeholder="Search name, email or message"><button type="submit" aria-label="Search conversations">@include('admin.icon',['icon'=>'arrow'])</button></div>
  <div class="cx-list-tools"><details class="cx-filter">
   <summary>@include('admin.icon',['icon'=>'filter']) Filters <span>{{ count(array_filter(array_diff_key($filters,['view'=>true,'q'=>true,'sort'=>true,'section'=>true])))?:'' }}</span></summary>
   <div class="cx-filter-body"><div class="cx-filter-heading"><strong>Filter conversations</strong><a href="{{ url('/admin/inbox') }}">Clear all</a></div><div class="cx-filter-grid">
    <label>View<select name="view"><option value="all">All conversations</option>@foreach(['unread'=>'Unread','recent'=>'Recent','starred'=>'Starred','mine'=>'Assigned to me','following'=>'Following','drafts'=>'Reply drafts','archived'=>'Archived'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['view']??'all')===$value)>{{ $label }}</option>@endforeach</select></label>
    <label>Type<select name="kind"><option value="">All submissions</option>@foreach(['quotes'=>'Quote requests','contacts'=>'Contact enquiries','feedback'=>'Feedback'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['kind']??'')===$value)>{{ $label }}</option>@endforeach</select></label>
    <label>Completion<select name="completion"><option value="">All progress</option><option value="incomplete" @selected(($filters['completion']??'')==='incomplete')>Partial leads</option><option value="completed" @selected(($filters['completion']??'')==='completed')>Completed</option></select></label>
    <label>Status<select name="status"><option value="">Active inbox</option>@foreach(['new','reviewed','contacted','resolved','spam','trash'] as $status)<option value="{{ $status }}" @selected(($filters['status']??'')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
    <label>Owner<select name="assigned_to"><option value="">Any owner</option><option value="unassigned" @selected(($filters['assigned_to']??'')==='unassigned')>Unassigned</option><option value="me" @selected(($filters['assigned_to']??'')==='me')>Assigned to me</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected(($filters['assigned_to']??'')===(string)$owner->id)>{{ $owner->name }}</option>@endforeach</select></label>
    <label>Tag<select name="tag"><option value="">All tags</option>@foreach($allTags as $tag)<option value="{{ $tag->id }}" @selected((string)($filters['tag']??'')===(string)$tag->id)>{{ $tag->name }}</option>@endforeach</select></label>
    <label>Source<select name="source"><option value="">All sources</option><option value="wordpress" @selected(($filters['source']??'')==='wordpress')>Imported WordPress</option><option value="current" @selected(($filters['source']??'')==='current')>Current website</option><option value="twilio" @selected(($filters['source']??'')==='twilio')>Twilio SMS / calls</option></select></label>
    <label>Contact preference<select name="email_dnd"><option value="">All preferences</option><option value="1" @selected(($filters['email_dnd']??'')==='1')>Do not email</option><option value="0" @selected(($filters['email_dnd']??'')==='0')>Email allowed</option></select></label>
    <label>Service<input name="service" value="{{ $filters['service']??'' }}" placeholder="e.g. auto"></label><label>From<input type="date" name="from" value="{{ $filters['from']??'' }}"></label><label>To<input type="date" name="to" value="{{ $filters['to']??'' }}"></label>
   </div><button class="cx-primary" type="submit">Apply filters</button></div>
  </details>
  <label class="cx-sort">@include('admin.icon',['icon'=>'sort'])<span class="cx-sr-only">Sort conversations</span><select name="sort" data-inbox-sort>@foreach(['activity_desc'=>'Latest activity','activity_asc'=>'Oldest activity','received_desc'=>'Newest received','received_asc'=>'Oldest received','unread'=>'Unread first'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['sort']??'activity_desc')===$value)>{{ $label }}</option>@endforeach</select></label></div>
 </form>
 <div class="cx-selection-bar"><label><input type="checkbox" data-select-all> Select all <span data-selection-count></span></label><span>{{ $entries->total() }} results</span></div>
 <form id="cx-bulk" method="post" action="{{ url('/admin/inbox/bulk') }}" class="cx-bulk-bar" hidden>@csrf<label class="cx-sr-only" for="cx-bulk-action">Bulk action</label><select name="action" id="cx-bulk-action" data-bulk-action><option value="read">Mark read</option><option value="unread">Mark unread</option><option value="star">Star</option><option value="unstar">Remove star</option><option value="assign">Assign owner</option><option value="reviewed">Mark reviewed</option><option value="contacted">Mark contacted</option><option value="archive">Archive</option><option value="restore">Restore to inbox</option></select><select name="assigned_to" aria-label="Assign selected conversations" data-bulk-owner hidden><option value="">Choose owner</option>@foreach($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select><button type="submit">Apply</button></form>
 <div class="cx-conversation-list">
 @forelse($entries as $entry)
  @php($active=$item&&$item['kind']===$entry['kind']&&$item['id']===$entry['id'])
  <article class="cx-conversation {{ $active?'selected':'' }} {{ $entry['unread']&&!$active?'unread':'' }}" @if($active) aria-current="true" @endif>
   <label class="cx-row-checkbox"><input type="checkbox" name="selected[]" value="{{ $entry['kind'].':'.$entry['id'] }}" form="cx-bulk" aria-label="Select {{ $entry['display_name'] }}" data-select-conversation></label>
   @php($preview=preg_split('/\R/',trim($entry['preview']?:($entry['message']?:'Requested '.$entry['subject'].'.')))[0]??'')
   <a class="cx-conversation-link" href="{{ url($entry['url']).'?'.http_build_query($filters) }}"><span class="cx-avatar {{ $entry['kind'] }}">{{ mb_strtoupper(mb_substr($entry['display_name'],0,1)) }}</span><div class="cx-conversation-main"><div class="cx-person-line"><strong>{{ $entry['display_name'] }}</strong><time>{{ $entry['activity_at']->isToday()?$entry['activity_at']->format('g:i A'):$entry['activity_at']->format('M j') }}</time></div><div class="cx-preview" title="{{ $preview }}">{{ $preview }}</div></div></a>
   @if($entry['unread']&&!$active)<i class="cx-unread-dot cx-row-unread" aria-label="Unread"></i>@endif
   <form class="cx-row-star" method="post" action="{{ url($entry['url'].'/manage') }}">@csrf<input type="hidden" name="starred" value="{{ $entry['starred']?0:1 }}"><button type="submit" title="{{ $entry['starred']?'Remove star':'Star conversation' }}" aria-label="{{ $entry['starred']?'Unstar':'Star' }} {{ $entry['display_name'] }}" class="{{ $entry['starred']?'starred':'' }}">@include('admin.icon',['icon'=>'star'])</button></form>
  </article>
 @empty
  <div class="cx-list-empty">@include('admin.icon',['icon'=>'inbox'])<h3>{{ array_sum($counts)?'No matching submissions':'Your inbox is ready' }}</h3><p>Try another search or clear your filters.</p><a href="{{ url('/admin/inbox') }}">Clear filters</a></div>
 @endforelse
 </div><div class="cx-list-pagination">{{ $entries->links('pagination.cms') }}</div>
</aside>
