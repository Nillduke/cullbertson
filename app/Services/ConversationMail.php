<?php
namespace App\Services;

use App\Mail\ConversationReply;
use App\Models\{InboxMessage,InboxConversation,MailSetting,MailDelivery};
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Validation\ValidationException;

class ConversationMail {
 public function readiness(array $item):array {
  $paused=InboxConversation::where('kind',$item['kind'])->where('source_id',$item['id'])->value('email_dnd');
  $s=MailSetting::find(1);
  $reason=$paused?'Email is paused by the Do not contact setting for this conversation.':(!filter_var($item['email'],FILTER_VALIDATE_EMAIL)?'This lead has no valid customer email address.':(!$s?->enabled?'Enable email sending in Email & SMTP.':($s->provider==='log'?'Choose a sending SMTP provider; log mode cannot email customers.':($s->missing(true)?'Complete the sender and SMTP settings in Email & SMTP.':''))));
  return ['ready'=>$reason==='','reason'=>$reason,'from'=>$s?->from_address??'','to'=>$item['email']];
 }
 public function enqueue(InboxMessage $message,array $item):MailDelivery {
  if(!($state=$this->readiness($item))['ready'])throw ValidationException::withMessages(['body'=>$state['reason']]);
  abort_unless($message->channel==='reply',422);
  $s=MailSetting::current();
  $delivery=MailDelivery::firstOrCreate(['dedupe_key'=>'inbox-reply:'.$message->id],[
   'kind'=>'inbox_reply','source_id'=>$message->id,'event'=>'reply','reference'=>$item['reference'],
   'recipients'=>[$item['email']],'subject'=>mb_substr(preg_replace('/[\r\n]+/',' ',($s->from_name?:'Culbertson Agency').' - '.$item['subject'].' ['.$item['reference'].']'),0,240),
  ]);
  if($delivery->wasRecentlyCreated)$message->update(['status'=>'queued']);
  return $delivery;
 }
 public function deliver(int $id):MailDelivery {
  $row=MailDelivery::findOrFail($id);$s=MailSetting::current();
  if(!$s->enabled||$s->provider==='log'||$s->missing(true))return $row;
  $claimed=MailDelivery::whereKey($id)->where(fn($q)=>$q->whereIn('status',['pending','failed'])->orWhere(fn($q)=>$q->where('status','sending')->where('locked_at','<',now()->subMinutes(5))))->update(['status'=>'sending','locked_at'=>now(),'attempted_at'=>now(),'attempts'=>DB::raw('attempts + 1'),'updated_at'=>now()]);
  if(!$claimed)return $row->fresh();
  $row->refresh();$message=InboxMessage::find($row->source_id);
  try{
   if(!$message||$message->channel!=='reply')throw new \RuntimeException('Reply no longer exists.');
   $message->update(['status'=>'sending']);
   $conversation=InboxConversation::findOrFail($message->conversation_id);
   if($conversation->email_dnd){$row->update(['status'=>'blocked','locked_at'=>null,'last_error'=>'Email paused by Do not contact.','next_attempt_at'=>null]);$message->update(['status'=>'blocked']);return $row->fresh();}
   $record=app(InboxWorkspace::class)->record($conversation->kind,$conversation->source_id);
   $item=app(SubmissionPresentation::class)->summarize($record,$conversation->kind);
   $mail=new ConversationReply($message->body,$item['name_provided']?$item['name']:'',$row->reference,$row->subject,$row->id,parse_url(config('app.url'),PHP_URL_HOST)?:'localhost');
   $messageId=app(AdminMailTransport::class)->send($s,$row->recipients,$mail);
   if(!$messageId)throw new \RuntimeException('The mail transport did not accept the message.');
   $row->update(['status'=>'accepted','message_id'=>$messageId,'sent_at'=>now(),'locked_at'=>null,'last_error'=>null,'next_attempt_at'=>null]);
   $message->update(['status'=>'accepted']);
  }catch(\Throwable $error){
   $delay=[60,300,900,3600,21600][min(4,$row->attempts-1)];
   $row->update(['status'=>'failed','locked_at'=>null,'last_error'=>AdminMailTransport::safeError($error),'next_attempt_at'=>$row->attempts<5?now()->addSeconds($delay):null]);
   $message?->update(['status'=>'failed']);
   Log::warning('Conversation reply could not be sent.',['delivery_id'=>$row->id,'exception_class'=>get_class($error)]);
  }
  return $row->fresh();
 }
}
