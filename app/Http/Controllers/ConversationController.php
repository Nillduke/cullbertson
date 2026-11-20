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
  $filters=$request->validate(['view'=>'nullable|in:all,unread,recent,starred','kind'=>'nullable|in:quotes,contacts,feedback','completion'=>'nullable|in:incomplete,completed','status'=>'nullable|in:new,reviewed,contacted,resolved,spam,trash','source'=>'nullable|in:wordpress,current','service'=>'nullable|string|max:80','q'=>'nullable|string|max:200','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d'.($request->filled('from')?'|after_or_equal:from':'')]);
  $query=$workspace->query();
  foreach(['kind','status','service'] as $key)if(!empty($filters[$key]))$query->where('s.'.$key,$filters[$key]);
  if(!empty($filters['completion']))$query->where('s.completion_status',$filters['completion']);
  if(($filters['source']??'')==='wordpress')$query->where(fn($q)=>$q->where('s.reference','like','WP-GF-%')->orWhere('s.reference','like','WP-EL-%'));
  if(($filters['source']??'')==='current')$query->where('s.reference','not like','WP-GF-%')->where('s.reference','not like','WP-EL-%');
  if(!empty($filters['q']))$query->where(fn($q)=>$q->where('s.reference','like','%'.$filters['q'].'%')->orWhere('s.payload','like','%'.$filters['q'].'%'));
  if(!empty($filters['from']))$query->whereDate('s.created_at','>=',$filters['from']);
  if(!empty($filters['to']))$query->whereDate('s.created_at','<=',$filters['to']);
  if(($filters['view']??'')==='unread')$workspace->unread($query);
  if(($filters['view']??'')==='starred')$query->where('c.starred',true);
  $activity='CASE WHEN c.last_message_at > COALESCE(s.updated_at,s.created_at) THEN c.last_message_at ELSE COALESCE(s.updated_at,s.created_at) END';
  if(($filters['view']??'')==='recent')$query->whereRaw($activity.' >= ?',[now()->subDays(7)->toDateTimeString()]);
  $entries=$query->select('s.*','c.read_at','c.starred')->selectRaw($activity.' as activity_at')->selectRaw('(SELECT body FROM inbox_messages WHERE conversation_id=c.id ORDER BY id DESC LIMIT 1) as last_preview')->orderByDesc('activity_at')->orderByDesc('s.id')->orderBy('s.kind')->paginate(20)->withPath(url('/admin/inbox'))->withQueryString();
  $entries->setCollection($entries->getCollection()->map(function($r)use($presenter){return $presenter->summarize($r,$r->kind)+['unread'=>!$r->read_at||$r->updated_at>$r->read_at,'starred'=>(bool)$r->starred,'preview'=>$r->last_preview,'activity_at'=>\Illuminate\Support\Carbon::parse($r->activity_at)];}));
  $first=$entries->first();$selectedKind??=$first['kind']??null;$selectedId??=$first['id']??null;
  $detail=$selectedKind?$workspace->detail($selectedKind,$selectedId,$presenter):['item'=>null];
  $counts=[];foreach(InboxWorkspace::MODELS as $kind=>$model)$counts[$kind]=$model::count();
  return response()->view('admin.conversations',$detail+['entries'=>$entries,'counts'=>$counts,'filters'=>$filters,'unreadCount'=>$workspace->unread($workspace->query())->count(),'newCount'=>array_sum($counts)])->header('Cache-Control','private, no-store');
 }
 public function manage(Request $request,string $kind,int $id,InboxWorkspace $workspace){
  $workspace->record($kind,$id);$data=$request->validate(['starred'=>'sometimes|boolean','assigned_to'=>['sometimes','nullable','integer',Rule::exists('users','id')->where('is_admin',true)],'read'=>'sometimes|in:read,unread']);
  $conversation=$workspace->conversation($kind,$id);$values=array_intersect_key($data,array_flip(['starred','assigned_to']));if(isset($data['read']))$values['read_at']=$data['read']==='unread'?null:now();$conversation->update($values);
  return redirect(($data['read']??'')==='unread'?url('/admin/inbox').'?view=unread':url('/admin/inbox/'.$kind.'/'.$id))->with('success','Conversation updated.');
 }
 public function message(Request $request,string $kind,int $id,InboxWorkspace $workspace){
  $record=$workspace->record($kind,$id);$data=$request->validate(['channel'=>'required|in:reply,note','body'=>'required|string|max:10000','client_token'=>'required|uuid','message_id'=>'nullable|integer|min:1','delivery_action'=>'nullable|in:draft,send']);
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
 public function thread(string $kind,int $id,InboxWorkspace $workspace,SubmissionPresentation $presenter){
  $detail=$workspace->detail($kind,$id,$presenter,false);
  $lead=array_intersect_key($detail['item'],array_flip(['display_name','name_provided','email','phone','form_title','subject','reference','completion_status','progress_percent','last_step_label','message']));
  $parts=['html'=>view('admin.conversation-thread',$detail)->render(),'contact_html'=>view('admin.conversation-contact',$detail)->render(),'answers_html'=>view('admin.conversation-answers',$detail)->render(),'lead'=>$lead,'outgoing_mail'=>$detail['outgoingMail']];
  $parts['version']=hash('sha256',json_encode($parts));
  return response()->json($parts)->header('Cache-Control','private, no-store');
 }
}
