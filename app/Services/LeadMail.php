<?php
namespace App\Services;
use App\Models\{MailSetting,MailDelivery,QuoteRequest,ContactEnquiry,FeedbackEntry};
use App\Mail\AgencyNotification;
use App\Jobs\SendAgencyMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Support\Str;

class LeadMail {
 public function enqueue(Model $record,string $event):?MailDelivery {
  $s=MailSetting::current();if(!$s->enabled||$s->missing())return null;
  $kind=match(true){$record instanceof QuoteRequest=>'quotes',$record instanceof ContactEnquiry=>'contacts',$record instanceof FeedbackEntry=>'feedback',default=>null};
  if(!$kind)return null;
  $enabled=match($kind){'quotes'=>$event==='partial'?$s->notify_partials:$s->notify_quotes,'contacts'=>$s->notify_contacts,'feedback'=>$s->notify_feedback};
  if(!$enabled)return null;
  $delivery=MailDelivery::firstOrCreate(['dedupe_key'=>$kind.':'.$record->id.':'.$event],[
   'kind'=>$kind,'source_id'=>$record->id,'event'=>$event,'reference'=>$record->reference,
   'recipients'=>$s->recipients,'subject'=>$s->subject_prefix.' — '.($event==='partial'?'Partial lead':($kind==='quotes'?'Completed quote':'New '.$kind)).' — '.$record->reference,
  ]);
  if($delivery->wasRecentlyCreated)DB::afterCommit(fn()=>SendAgencyMail::dispatch($delivery->id)->afterResponse());
  return $delivery;
 }
 public function test(string $recipient):MailDelivery {
  $s=MailSetting::current();
  $delivery=MailDelivery::create(['dedupe_key'=>'test:'.Str::uuid(),'kind'=>'test','event'=>'test','reference'=>'SMTP-'.strtoupper(bin2hex(random_bytes(4))),'recipients'=>[$recipient],'subject'=>$s->subject_prefix.' — SMTP test']);
  return $this->deliver($delivery->id);
 }
 public function deliver(int $id):MailDelivery {
  $s=MailSetting::current();$row=MailDelivery::findOrFail($id);
  if($row->kind!=='test'&&!$s->enabled)return $row;
  if($s->missing($row->kind==='test'))return $row;
  $claimed=MailDelivery::whereKey($id)->where(function($q){$q->whereIn('status',['pending','failed'])->orWhere(fn($q)=>$q->where('status','sending')->where('locked_at','<',now()->subMinutes(5)));})->update(['status'=>'sending','locked_at'=>now(),'attempted_at'=>now(),'attempts'=>DB::raw('attempts + 1'),'updated_at'=>now()]);
  if(!$claimed)return $row->fresh();
  $row->refresh();
  try{
   $details=$this->details($row,$s);
   $messageId=app(AdminMailTransport::class)->send($s,$row->recipients,new AgencyNotification($details,$row->subject,parse_url($s->website_url?:config('app.url'),PHP_URL_HOST)?:'localhost'));
   $row->update(['status'=>$s->provider==='log'?'logged':'accepted','message_id'=>$messageId,'sent_at'=>now(),'locked_at'=>null,'last_error'=>null,'next_attempt_at'=>null]);
  }catch(\Throwable $e){
   $delay=[60,300,900,3600,21600][min(4,$row->attempts-1)];
   $row->update(['status'=>'failed','locked_at'=>null,'last_error'=>AdminMailTransport::safeError($e),'next_attempt_at'=>$row->attempts<5?now()->addSeconds($delay):null]);
   Log::warning('Agency notification could not be sent.',['delivery_id'=>$row->id,'exception_class'=>get_class($e)]);
  }
  return $row->fresh();
 }
 public function pending(int $limit=20):int {
  if(!MailSetting::current()->enabled)return 0;
  $ids=MailDelivery::where('attempts','<',5)->where(function($q){$q->where('status','pending')->orWhere(fn($q)=>$q->where('status','failed')->whereNotNull('next_attempt_at')->where('next_attempt_at','<=',now()))->orWhere(fn($q)=>$q->where('status','sending')->where('locked_at','<',now()->subMinutes(5)));})->orderBy('id')->limit($limit)->pluck('id');
  foreach($ids as $id)$this->deliver($id);
  return $ids->count();
 }
 private function details(MailDelivery $row,MailSetting $s):array {
  $base=rtrim($s->website_url?:config('app.url'),'/');
  $details=['deliveryId'=>$row->id,'category'=>'SMTP test','heading'=>'Your mail connection is ready to test.','intro'=>'This is a test notification from your Culbertson Agency admin workspace.','rows'=>['Reference'=>$row->reference,'Transport'=>$s->provider,'Created'=>$row->created_at->toDateTimeString()],'url'=>$base.'/admin/mail','linkLabel'=>'Open email settings','note'=>'SMTP acceptance is not proof of inbox delivery. Check your inbox and spam folder.'];
  if($row->kind==='test')return $details;
  $model=match($row->kind){'quotes'=>QuoteRequest::class,'contacts'=>ContactEnquiry::class,'feedback'=>FeedbackEntry::class};
  $record=$model::find($row->source_id)??throw new \RuntimeException('Source record no longer exists.');
  $item=app(SubmissionPresentation::class)->summarize($record,$row->kind);
  $partial=$row->event==='partial';
  $details['category']=$partial?'Partial lead':($row->kind==='quotes'?'Completed quote':'New enquiry');
  $details['heading']=$partial?'A visitor started a quote request.':'A new submission is ready to review.';
  $details['intro']=$partial?'The visitor has not finished the form. This is the first saved lead notification, not a completed quote.':'The submission has been saved in your private admin inbox.';
  $details['rows']=['Reference'=>$row->reference,'Type'=>$item['subject'],'Received'=>$record->created_at->toDateTimeString(),'Completion'=>$partial?'Incomplete':'Submitted'];
  $sensitive=str_starts_with($row->reference??'','PRV-')||($record->payload['topic']??'')==='Document submission';
  if($s->include_contact&&!$sensitive)foreach(['Name'=>'name','Email'=>'email','Phone'=>'phone'] as $label=>$key)if($item[$key])$details['rows'][$label]=$item[$key];
  if($row->kind==='quotes'){
   $details['rows']['Form progress']=$item['progress_percent'].'%';
   if($partial&&$item['last_step_label'])$details['rows']['Last saved step']=$item['last_step_label'];
  }
  if($s->include_contact&&!$sensitive){
   if($item['message']!=='')$details['rows']['Message']=$item['message'];
   if($item['rating']!==null)$details['rows']['Rating']=(string)$item['rating'];
  }
  $details['url']=$base.'/admin/inbox/'.$row->kind.'/'.$row->source_id;$details['linkLabel']='Review submission';
  $details['note']='Sign in to review full details. This notification does not bind insurance coverage.';
  return $details;
 }
}
