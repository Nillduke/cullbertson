<?php
namespace App\Http\Controllers;

use App\Models\InboxMessage;
use App\Services\{InboxWorkspace,SubmissionPresentation};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConversationController extends Controller {
 public function index(Request $request,InboxWorkspace $workspace,SubmissionPresentation $presenter){return $this->render($request,$workspace,$presenter);}
 public function show(Request $request,string $kind,int $id,InboxWorkspace $workspace,SubmissionPresentation $presenter){return $this->render($request,$workspace,$presenter,$kind,$id);}
 private function render(Request $request,InboxWorkspace $workspace,SubmissionPresentation $presenter,?string $selectedKind=null,?int $selectedId=null){
  $filters=$request->validate(['view'=>'nullable|in:all,unread,recent,starred,mine,following,drafts,archived','section'=>'nullable|in:conversations,actions,analytics,settings','sort'=>'nullable|in:activity_desc,activity_asc,received_desc,received_asc,unread','assigned_to'=>['nullable','regex:/^(me|unassigned|[1-9][0-9]*)$/'],'tag'=>'nullable|integer|exists:inbox_tags,id','email_dnd'=>'nullable|in:0,1','kind'=>'nullable|in:quotes,contacts,feedback','completion'=>'nullable|in:incomplete,completed','status'=>'nullable|in:new,reviewed,contacted,resolved,spam,trash','source'=>'nullable|in:wordpress,current,twilio','service'=>'nullable|string|max:80','q'=>'nullable|string|max:200','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d'.($request->filled('from')?'|after_or_equal:from':'')]);
  $query=$workspace->query();
  if(($filters['view']??'')==='archived')$query->where('s.status','trash');elseif(empty($filters['status']))$query->whereNotIn('s.status',['trash','spam']);
  if(($filters['view']??'')==='mine')$query->where('c.assigned_to',$request->user()->id);
  if(($filters['view']??'')==='following')$query->whereExists(fn($q)=>$q->selectRaw('1')->from('inbox_conversation_followers as f')->whereColumn('f.inbox_conversation_id','c.id')->where('f.user_id',$request->user()->id));
  if(($filters['view']??'')==='drafts')$query->whereExists(fn($q)=>$q->selectRaw('1')->from('inbox_messages as m')->whereColumn('m.conversation_id','c.id')->where('m.status','draft'));
  if(isset($filters['assigned_to'])){if($filters['assigned_to']==='unassigned')$query->whereNull('c.assigned_to');else $query->where('c.assigned_to',$filters['assigned_to']==='me'?$request->user()->id:(int)$filters['assigned_to']);}
  if(!empty($filters['tag']))$query->whereExists(fn($q)=>$q->selectRaw('1')->from('inbox_conversation_tag as t')->whereColumn('t.inbox_conversation_id','c.id')->where('t.inbox_tag_id',$filters['tag']));
  if(isset($filters['email_dnd']))$query->whereRaw('COALESCE(c.email_dnd,0) = ?',[(int)$filters['email_dnd']]);
  foreach(['kind','status','service'] as $key)if(!empty($filters[$key]))$query->where('s.'.$key,$filters[$key]);
  if(!empty($filters['completion']))$query->where('s.completion_status',$filters['completion']);
  if(($filters['source']??'')==='wordpress')$query->where(fn($q)=>$q->where('s.reference','like','WP-GF-%')->orWhere('s.reference','like','WP-EL-%'));
  if(($filters['source']??'')==='current')$query->where('s.reference','not like','WP-GF-%')->where('s.reference','not like','WP-EL-%')->whereRaw("COALESCE(json_extract(s.payload,'$.origin'),'') != 'twilio'");
  if(($filters['source']??'')==='twilio')$query->whereRaw("json_extract(s.payload,'$.origin') = 'twilio'");
  if(!empty($filters['q']))$query->where(fn($q)=>$q->where('s.reference','like','%'.$filters['q'].'%')->orWhere('s.payload','like','%'.$filters['q'].'%')->orWhereExists(fn($m)=>$m->selectRaw('1')->from('inbox_messages as m')->whereColumn('m.conversation_id','c.id')->where('m.body','like','%'.$filters['q'].'%')));
  if(!empty($filters['from']))$query->whereDate('s.created_at','>=',$filters['from']);
  if(!empty($filters['to']))$query->whereDate('s.created_at','<=',$filters['to']);
  if(($filters['view']??'')==='unread')$workspace->unread($query);
  if(($filters['view']??'')==='starred')$query->where('c.starred',true);
  $activity='CASE WHEN c.last_message_at > COALESCE(s.updated_at,s.created_at) THEN c.last_message_at ELSE COALESCE(s.updated_at,s.created_at) END';
  if(($filters['view']??'')==='recent')$query->whereRaw($activity.' >= ?',[now()->subDays(7)->toDateTimeString()]);
  $sort=$filters['sort']??'activity_desc';
  if($sort==='unread')$query->orderByRaw('CASE WHEN c.read_at IS NULL OR s.updated_at>c.read_at THEN 0 ELSE 1 END');
  $query->orderBy(in_array($sort,['received_desc','received_asc'])?'s.created_at':DB::raw($activity),in_array($sort,['activity_asc','received_asc'])?'asc':'desc');
  $entries=$query->select('s.*','c.read_at','c.starred')->selectRaw($activity.' as activity_at')->selectRaw('(SELECT body FROM inbox_messages WHERE conversation_id=c.id ORDER BY id DESC LIMIT 1) as last_preview')->orderByDesc('s.id')->orderBy('s.kind')->paginate(20)->withPath(url('/admin/inbox'))->withQueryString();
  $entries->setCollection($entries->getCollection()->map(function($r)use($presenter){return $presenter->summarize($r,$r->kind)+['unread'=>!$r->read_at||$r->updated_at>$r->read_at,'starred'=>(bool)$r->starred,'preview'=>$r->last_preview,'activity_at'=>\Illuminate\Support\Carbon::parse($r->activity_at)];}));
  $first=$entries->first();$selectedKind??=$first['kind']??null;$selectedId??=$first['id']??null;
  $detail=$selectedKind&&($filters['section']??'conversations')==='conversations'?$workspace->detail($selectedKind,$selectedId,$presenter):['item'=>null];
  $counts=[];foreach(InboxWorkspace::MODELS as $kind=>$model)$counts[$kind]=$model::count();
  $tools=['owners'=>\App\Models\User::where('is_admin',true)->orderBy('name')->get(['id','name']),'allTags'=>\App\Models\InboxTag::orderBy('name')->get(),'section'=>$filters['section']??'conversations','mailSettings'=>\App\Models\MailSetting::find(1)];
  if($tools['section']!=='conversations')$tools['metrics']=$this->metrics($workspace);
  return response()->view('admin.conversations',$detail+$tools+['entries'=>$entries,'counts'=>$counts,'filters'=>$filters,'unreadCount'=>$workspace->unread($workspace->query())->whereNotIn('s.status',['trash','spam'])->count(),'newCount'=>array_sum($counts)])->header('Cache-Control','private, no-store');
 }
 private function metrics(InboxWorkspace $workspace):array {
  return ['total'=>$workspace->query()->count(),'unassigned'=>$workspace->query()->whereNull('c.assigned_to')->whereNotIn('s.status',['spam','trash'])->count(),'partials'=>\App\Models\QuoteRequest::where('completion_status','incomplete')->whereNotIn('status',['spam','trash'])->count(),'completed'=>\App\Models\QuoteRequest::where('completion_status','completed')->count(),'drafts'=>InboxMessage::where('status','draft')->count(),'accepted'=>\App\Models\MailDelivery::where('kind','inbox_reply')->where('status','accepted')->count(),'failed'=>\App\Models\MailDelivery::where('kind','inbox_reply')->where('status','failed')->count(),'today'=>$workspace->query()->whereDate('s.created_at',today())->count(),'by_status'=>$workspace->query()->select('s.status')->selectRaw('COUNT(*) as count')->groupBy('s.status')->pluck('count','status')->all()];
 }
 public function manage(Request $request,string $kind,int $id,InboxWorkspace $workspace){
  $workspace->record($kind,$id);$data=$request->validate(['starred'=>'sometimes|boolean','assigned_to'=>['sometimes','nullable','integer',Rule::exists('users','id')->where('is_admin',true)],'read'=>'sometimes|in:read,unread','tags'=>'sometimes|nullable|string|max:500','followers'=>'sometimes|nullable|array|max:30','followers.*'=>['integer','distinct',Rule::exists('users','id')->where('is_admin',true)],'email_dnd'=>'sometimes|boolean','sms_dnd'=>'sometimes|boolean','voice_dnd'=>'sometimes|boolean']);
  $conversation=$workspace->conversation($kind,$id);
  DB::transaction(function()use($data,$conversation){
   $values=array_intersect_key($data,array_flip(['starred','assigned_to','email_dnd','sms_dnd','voice_dnd']));if(isset($data['read']))$values['read_at']=$data['read']==='unread'?null:now();$conversation->update($values);
   if(array_key_exists('tags',$data)){
    $names=array_values(array_unique(array_filter(array_map('trim',explode(',',$data['tags']??'')))));
    if(count($names)>12||collect($names)->contains(fn($name)=>mb_strlen($name)>40))throw \Illuminate\Validation\ValidationException::withMessages(['tags'=>'Use up to 12 tags, each no longer than 40 characters.']);
    $ids=[];foreach($names as $name)$ids[]=\App\Models\InboxTag::firstOrCreate(['slug'=>hash('sha256',mb_strtolower($name))],['name'=>$name])->id;$conversation->tags()->sync($ids);
   }
   if(array_key_exists('followers',$data))$conversation->followers()->sync($data['followers']??[]);
  });
  return redirect(($data['read']??'')==='unread'?url('/admin/inbox').'?view=unread':url('/admin/inbox/'.$kind.'/'.$id))->with('success','Conversation updated.');
 }
 public function bulk(Request $request,InboxWorkspace $workspace){
  $data=$request->validate(['selected'=>'required|array|min:1|max:100','selected.*'=>['required','string','distinct','regex:/^(quotes|contacts|feedback):[1-9][0-9]*$/'],'action'=>'required|in:read,unread,star,unstar,assign,archive,restore,reviewed,contacted','assigned_to'=>['nullable',Rule::requiredIf($request->input('action')==='assign'),'integer',Rule::exists('users','id')->where('is_admin',true)]]);
  DB::transaction(function()use($data,$workspace){
   foreach($data['selected'] as $key){[$kind,$id]=explode(':',$key);$record=$workspace->record($kind,(int)$id);
    if(in_array($data['action'],['archive','restore','reviewed','contacted'])){$record->update(['status'=>match($data['action']){'archive'=>'trash','restore'=>'new',default=>$data['action']}]);continue;}
    $conversation=$workspace->conversation($kind,(int)$id);$conversation->update(match($data['action']){'read'=>['read_at'=>now()],'unread'=>['read_at'=>null],'star'=>['starred'=>true],'unstar'=>['starred'=>false],'assign'=>['assigned_to'=>$data['assigned_to']]});
   }
  });
  return redirect(url('/admin/inbox'))->with('success',count($data['selected']).' conversation(s) updated.');
 }
 public function message(Request $request,string $kind,int $id,InboxWorkspace $workspace){
  $record=$workspace->record($kind,$id);$data=$request->validate(['channel'=>'required|in:reply,note,sms','body'=>'required|string|max:10000','client_token'=>'required|uuid','message_id'=>'nullable|integer|min:1','delivery_action'=>'nullable|in:draft,send']);
  if($data['channel']==='sms')return $this->sms($request,$kind,$id,$workspace,$record,$data);
  $send=($data['delivery_action']??'draft')==='send';$mailer=app(\App\Services\ConversationMail::class);
  $item=app(SubmissionPresentation::class)->summarize($record,$kind);
  if($send){abort_unless($data['channel']==='reply',422);if(!($state=$mailer->readiness($item))['ready'])throw \Illuminate\Validation\ValidationException::withMessages(['body'=>$state['reason']]);}
  $conversation=$workspace->conversation($kind,$id);
  $delivery=DB::transaction(function()use($data,$request,$conversation,$send,$mailer,$item){
   if(!empty($data['message_id'])){$message=$conversation->messages()->whereKey($data['message_id'])->where('channel','reply')->where('status','draft')->lockForUpdate()->firstOrFail();abort_unless($data['channel']==='reply',422);$message->update(['body'=>$data['body']]);}
   else{$message=InboxMessage::firstOrCreate(['client_token'=>$data['client_token']],['conversation_id'=>$conversation->id,'user_id'=>$request->user()->id,'channel'=>$data['channel'],'status'=>$data['channel']==='reply'?'draft':'internal','body'=>$data['body']]);abort_unless($message->conversation_id===$conversation->id&&$message->user_id===$request->user()->id&&$message->channel===$data['channel'],409);}
   $conversation->update(['last_message_at'=>now(),'read_at'=>now()]);
   return $send?$mailer->enqueue($message,$item):null;
  });
  if($delivery){
   $result=$mailer->deliver($delivery->id);
   return redirect(url('/admin/inbox/'.$kind.'/'.$id))->with($result->status==='failed'?'mail_error':'success',match($result->status){'accepted'=>'Email accepted by the mail server. Check the client inbox and spam folder.','failed'=>'Email could not be sent: '.$result->last_error,default=>'Email queued for delivery. Its status will update in this conversation.'});
  }
  return redirect(url('/admin/inbox/'.$kind.'/'.$id))->with('success',$data['channel']==='reply'?'Reply saved as a draft; nothing has been sent.':'Internal note added.');
 }
 public function call(Request $request,string $kind,int $id,InboxWorkspace $workspace){
  $record=$workspace->record($kind,$id);$data=$request->validate(['client_token'=>'required|uuid']);$service=app(\App\Services\TwilioCommunications::class);$item=app(SubmissionPresentation::class)->summarize($record,$kind);
  if(!($state=$service->readiness($item,'call'))['ready'])throw \Illuminate\Validation\ValidationException::withMessages(['body'=>$state['reason']]);
  $conversation=$workspace->conversation($kind,$id);
  $delivery=DB::transaction(function()use($request,$conversation,$data,$service,$item){$message=InboxMessage::firstOrCreate(['client_token'=>$data['client_token']],['conversation_id'=>$conversation->id,'user_id'=>$request->user()->id,'channel'=>'call','status'=>'queued','body'=>'Call to '.$item['phone'].' via agency callback.']);abort_unless($message->conversation_id===$conversation->id&&$message->channel==='call'&&$message->user_id===$request->user()->id,409);$conversation->update(['last_message_at'=>now()]);return $service->enqueue($message,$item,'call');});
  $result=$service->deliver($delivery->id);
  return redirect(url('/admin/inbox/'.$kind.'/'.$id))->with($result->status==='failed'?'mail_error':'success',$result->status==='failed'?$result->last_error:'Call requested. Answer the agency callback phone, then press 1 to connect the customer.');
 }
 private function sms(Request $request,string $kind,int $id,InboxWorkspace $workspace,$record,array $data){
  $request->validate(['body'=>'required|string|max:1600']);$service=app(\App\Services\TwilioCommunications::class);$item=app(SubmissionPresentation::class)->summarize($record,$kind);$send=($data['delivery_action']??'draft')==='send';
  if($send&&!($state=$service->readiness($item))['ready'])throw \Illuminate\Validation\ValidationException::withMessages(['body'=>$state['reason']]);
  $conversation=$workspace->conversation($kind,$id);
  $delivery=DB::transaction(function()use($data,$request,$conversation,$service,$item,$send){
   if(!empty($data['message_id'])){$message=$conversation->messages()->whereKey($data['message_id'])->where('channel','sms')->where('status','draft')->lockForUpdate()->firstOrFail();$message->update(['body'=>$data['body']]);}
   else{$message=InboxMessage::firstOrCreate(['client_token'=>$data['client_token']],['conversation_id'=>$conversation->id,'user_id'=>$request->user()->id,'channel'=>'sms','status'=>'draft','body'=>$data['body']]);abort_unless($message->conversation_id===$conversation->id&&$message->channel==='sms'&&$message->user_id===$request->user()->id,409);}
   $conversation->update(['last_message_at'=>now(),'read_at'=>now()]);return $send?$service->enqueue($message,$item,'sms'):null;
  });
  if(!$delivery)return redirect(url('/admin/inbox/'.$kind.'/'.$id))->with('success','SMS draft saved; nothing has been sent.');
  $result=$service->deliver($delivery->id);return redirect(url('/admin/inbox/'.$kind.'/'.$id))->with($result->status==='failed'?'mail_error':'success',$result->status==='failed'?$result->last_error:'SMS submitted to Twilio. Delivery status will update in the conversation.');
 }
 public function thread(string $kind,int $id,InboxWorkspace $workspace,SubmissionPresentation $presenter){
  $detail=$workspace->detail($kind,$id,$presenter,false);
  $lead=array_intersect_key($detail['item'],array_flip(['display_name','name_provided','email','phone','form_title','subject','reference','completion_status','progress_percent','last_step_label','message']));
  $latest=$detail['messages']->last()?->body?:($detail['item']['message']?:'Requested '.$detail['item']['subject'].'.');$lead['preview']=preg_split('/\R/',trim($latest))[0]??'';
  $parts=['html'=>view('admin.conversation-thread',$detail)->render(),'contact_html'=>view('admin.conversation-contact',$detail)->render(),'answers_html'=>view('admin.conversation-answers',$detail)->render(),'lead'=>$lead,'outgoing_mail'=>$detail['outgoingMail'],'sms_state'=>$detail['smsState'],'voice_state'=>$detail['voiceState']];
  $parts['version']=hash('sha256',json_encode($parts));
  return response()->json($parts)->header('Cache-Control','private, no-store');
 }
}
