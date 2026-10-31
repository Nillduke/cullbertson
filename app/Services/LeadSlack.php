<?php
namespace App\Services;
use App\Models\{SlackSetting,SlackDelivery,QuoteRequest,ContactEnquiry,FeedbackEntry};
use App\Jobs\SendLeadSlack;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadSlack {
 public function enqueue(Model $record,string $event):?SlackDelivery {
  $s=SlackSetting::current();if(!$s->enabled||!$s->configured())return null;
  $kind=match(true){$record instanceof QuoteRequest=>'quotes',$record instanceof ContactEnquiry=>'contacts',$record instanceof FeedbackEntry=>'feedback',default=>null};if(!$kind)return null;
  if(!match($kind){'quotes'=>$event==='partial'?$s->notify_partials:$s->notify_quotes,'contacts'=>$s->notify_contacts,'feedback'=>$s->notify_feedback})return null;
  $row=SlackDelivery::firstOrCreate(['dedupe_key'=>$kind.':'.$record->id.':'.$event],['kind'=>$kind,'source_id'=>$record->id,'event'=>$event,'reference'=>$record->reference,'channel'=>$s->channel,'webhook_url'=>$s->webhook_url]);
  if($row->wasRecentlyCreated)DB::afterCommit(fn()=>SendLeadSlack::dispatch($row->id)->afterResponse());
  return $row;
 }
 public function test():SlackDelivery {
  $s=SlackSetting::current();$row=SlackDelivery::create(['dedupe_key'=>'test:'.Str::uuid(),'kind'=>'test','event'=>'test','reference'=>'SLACK-'.strtoupper(bin2hex(random_bytes(4))),'channel'=>$s->channel,'webhook_url'=>$s->webhook_url]);return $this->deliver($row->id);
 }
 public function deliver(int $id):SlackDelivery {
  $s=SlackSetting::current();$row=SlackDelivery::findOrFail($id);if($row->kind!=='test'&&!$s->enabled)return $row;
  $claimed=SlackDelivery::whereKey($id)->where(fn($q)=>$q->whereIn('status',['pending','failed'])->orWhere(fn($q)=>$q->where('status','sending')->where('locked_at','<',now()->subMinutes(5))))->update(['status'=>'sending','locked_at'=>now(),'attempted_at'=>now(),'attempts'=>DB::raw('attempts + 1'),'updated_at'=>now()]);
  if(!$claimed)return $row->fresh();$row->refresh();
  try{$result=app(SlackTransport::class)->send($row->webhook_url,$this->payload($row,$s));}
  catch(\Throwable){$result=['ok'=>false,'error'=>'The notification could not be prepared. Check the saved webhook and source submission.','retry'=>false];}
  if($result['ok'])$row->update(['status'=>'sent','sent_at'=>now(),'locked_at'=>null,'next_attempt_at'=>null,'last_error'=>null]);
  else{$delay=max([60,300,900,3600,21600][min(4,$row->attempts-1)],$result['retry_after']??0);$row->update(['status'=>'failed','locked_at'=>null,'last_error'=>$result['error'],'next_attempt_at'=>$row->kind!=='test'&&($result['retry']??false)&&$row->attempts<5?now()->addSeconds($delay):null]);}
  return $row->fresh();
 }
 public function pending(int $limit=20):int {
  if(!SlackSetting::current()->enabled)return 0;
  $ids=SlackDelivery::where('kind','!=','test')->where('attempts','<',5)->where(fn($q)=>$q->where('status','pending')->orWhere(fn($q)=>$q->where('status','failed')->whereNotNull('next_attempt_at')->where('next_attempt_at','<=',now()))->orWhere(fn($q)=>$q->where('status','sending')->where('locked_at','<',now()->subMinutes(5))))->orderBy('id')->limit($limit)->pluck('id');
  foreach($ids as $id)$this->deliver($id);return $ids->count();
 }
 private function payload(SlackDelivery $row,SlackSetting $s):array {
  if($row->kind==='test')return ['text'=>'Culbertson Agency: Slack connection test. Your integration can send messages to this webhook. Reference: '.$row->reference,'unfurl_links'=>false,'unfurl_media'=>false];
  $model=match($row->kind){'quotes'=>QuoteRequest::class,'contacts'=>ContactEnquiry::class,'feedback'=>FeedbackEntry::class};$record=$model::findOrFail($row->source_id);$item=app(SubmissionPresentation::class)->summarize($record,$row->kind);
  $heading=$row->event==='partial'?'Partial quote lead':($row->kind==='quotes'?'Completed quote request':'New '.($row->kind==='contacts'?'enquiry':'feedback'));
  $values=['Reference'=>$row->reference,'Service / form'=>$item['subject'],'Status'=>$row->event==='partial'?'Incomplete quote':'Submitted','Received'=>$record->created_at->format('Y-m-d H:i').' UTC'];
  $sensitive=str_starts_with($row->reference,'PRV-')||($record->payload['topic']??'')==='Document submission';
  if($s->include_contact&&!$sensitive)foreach(['Name'=>'name','Email'=>'email','Phone'=>'phone'] as $label=>$key)if($item[$key])$values[$label]=$item[$key];
  if($row->kind==='quotes')$values['Form progress']=$item['progress_percent'].'%';
  $fields=[];foreach($values as $label=>$value)$fields[]=['type'=>'plain_text','text'=>$label."\n".Str::limit((string)$value,1500),'emoji'=>false];
  return ['text'=>'Culbertson Agency — '.$heading.' — '.$row->reference,'unfurl_links'=>false,'unfurl_media'=>false,'blocks'=>[
   ['type'=>'header','text'=>['type'=>'plain_text','text'=>'Culbertson Agency · '.$heading,'emoji'=>false]],
   ['type'=>'section','fields'=>$fields],
   ['type'=>'context','elements'=>[['type'=>'plain_text','text'=>'Private agency notification. Full answers and uploaded files stay in the admin dashboard.']]],
  ]];
 }
}
