<?php
namespace App\Services;
use App\Models\{TwilioSetting,TwilioDelivery,InboxConversation,InboxMessage,ContactEnquiry};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Twilio\TwiML\VoiceResponse;

class TwilioCommunications {
 public static function publicUrl(string $path):string{return rtrim(config('app.url'),'/').'/'.ltrim($path,'/');}
 public function phone(string $phone):?string {
  $digits=preg_replace('/\D/','',$phone);if(strlen($digits)===10)$digits='1'.$digits;
  return preg_match('/^[1-9][0-9]{7,14}$/D',$digits)?'+'.$digits:null;
 }
 public function readiness(array $item,string $kind='sms'):array {
  $s=TwilioSetting::find(1);$to=$this->phone($item['phone']);$c=InboxConversation::where('kind',$item['kind'])->where('source_id',$item['id'])->first();
  $reason='';
  if(!$s?->enabled||!$s->credentialsReady())$reason='Set up and enable Twilio in Inbox settings.';
  elseif(!($kind==='sms'?$s->sms_enabled:$s->voice_enabled))$reason=$kind==='sms'?'SMS is disabled in Twilio settings.':'Voice calls are disabled in Twilio settings.';
  elseif(!$to)$reason='This contact has no valid phone number.';
  elseif($kind==='sms'&&$c?->sms_dnd)$reason='SMS is paused by Do not contact for this conversation.';
  elseif($kind==='call'&&$c?->voice_dnd)$reason='Calls are paused by Do not contact for this conversation.';
  elseif($kind==='sms'&&!$s->from_number&&!$s->messaging_service_sid)$reason='Set a Twilio SMS sender or Messaging Service SID.';
  elseif($kind==='call'&&(!$s->from_number||!$s->voice_agent_number))$reason='Set a Twilio voice number and agency callback number.';
  elseif($kind==='call'&&in_array($to,[$s->voice_agent_number,$s->from_number],true))$reason='The customer number must differ from the agency and Twilio numbers.';
  return ['ready'=>$reason==='','reason'=>$reason,'to'=>$to??'','from'=>$s?->from_number??'','agent'=>$s?->voice_agent_number??''];
 }
 public function enqueue(InboxMessage $message,array $item,string $kind):TwilioDelivery {
  $state=$this->readiness($item,$kind);if(!$state['ready'])throw ValidationException::withMessages(['body'=>$state['reason']]);
  $s=TwilioSetting::findOrFail(1);
  return TwilioDelivery::firstOrCreate(['inbox_message_id'=>$message->id],['inbox_conversation_id'=>$message->conversation_id,'kind'=>$kind,'direction'=>'outbound','from_number'=>$s->from_number?:'','to_number'=>$state['to'],'agent_number'=>$kind==='call'?$s->voice_agent_number:null]);
 }
 public function deliver(int $id):TwilioDelivery {
  $row=TwilioDelivery::findOrFail($id);$s=TwilioSetting::findOrFail(1);
  $claimed=TwilioDelivery::whereKey($id)->where('status','pending')->update(['status'=>'sending','locked_at'=>now()]);if(!$claimed)return $row->fresh();
  $row->refresh();$message=$row->message;
  try{
   $conversation=$row->conversation;$record=app(InboxWorkspace::class)->record($conversation->kind,$conversation->source_id);$item=app(SubmissionPresentation::class)->summarize($record,$conversation->kind);
   if(!($state=$this->readiness($item,$row->kind))['ready'])throw ValidationException::withMessages(['body'=>$state['reason']]);
   $message->update(['status'=>'sending']);
   if($row->kind==='sms')$result=app(TwilioGateway::class)->sms($s,$row->to_number,$message->body,self::publicUrl('/webhooks/twilio/status/'.$row->id));
   else{
    $voice=new VoiceResponse();$gather=$voice->gather(['numDigits'=>1,'timeout'=>15,'action'=>self::publicUrl('/webhooks/twilio/connect/'.$row->id),'method'=>'POST']);$gather->say('Culbertson Agency customer call. Press one to connect.');$voice->hangup();
    $result=app(TwilioGateway::class)->call($s,(string)$voice,self::publicUrl('/webhooks/twilio/status/'.$row->id.'?leg=agent'));
   }
   DB::transaction(function()use($row,$result,$message){$current=TwilioDelivery::lockForUpdate()->findOrFail($row->id);$values=['provider_sid'=>$result['sid'],'locked_at'=>null];if(in_array($current->status,['pending','sending','failed'],true))$values['status']=$result['status']?:'queued';if(!empty($result['from']))$values['from_number']=$result['from'];$current->update($values);$message->update(['status'=>$current->status]);});
  }catch(\Throwable $error){
   $row->refresh();if(in_array($row->status,['pending','sending'],true)){$row->update(['status'=>'failed','locked_at'=>null,'error_code'=>(int)$error->getCode()?:null,'last_error'=>$error instanceof ValidationException?collect($error->errors())->flatten()->first():TwilioGateway::error($error)]);$message?->update(['status'=>'failed']);}
  }
  return $row->fresh();
 }
 public function incomingConversation(string $from):InboxConversation {
  $prior=TwilioDelivery::where(fn($q)=>$q->where(fn($q)=>$q->where('direction','outbound')->where('to_number',$from))->orWhere(fn($q)=>$q->where('direction','inbound')->where('from_number',$from)))->orderByDesc('id')->first();if($prior)return $prior->conversation;
  $matches=[];$presenter=app(SubmissionPresentation::class);
  foreach(InboxWorkspace::MODELS as $kind=>$class){foreach($class::whereNotIn('status',['spam','trash'])->orderByDesc('id')->cursor() as $record){$item=$presenter->summarize($record,$kind);if($this->phone($item['phone'])===$from)$matches[]=[$kind,$record->id];if(count($matches)>1)break;}if(count($matches)>1)break;}
  if(count($matches)===1)return app(InboxWorkspace::class)->conversation(...$matches[0]);
  $lead=ContactEnquiry::withoutEvents(fn()=>ContactEnquiry::create(['reference'=>'TXT-'.strtoupper(bin2hex(random_bytes(6))),'payload'=>['name'=>'','phone'=>$from,'email'=>'','topic'=>'Twilio conversation','origin'=>'twilio','message'=>'']]));
  return app(InboxWorkspace::class)->conversation('contacts',$lead->id);
 }
 public function receiveSms(array $data):TwilioDelivery {
  return DB::transaction(function()use($data){
   if($existing=TwilioDelivery::where('provider_sid',$data['MessageSid'])->first())return $existing;
   $from=$this->phone($data['From']);$conversation=$this->incomingConversation($from);
   $body=mb_substr($data['Body']??'',0,1600);if(($data['NumMedia']??0)>0)$body.=($body?"\n\n":'').'[Media received; view the attachment in Twilio Console.]';
   $message=InboxMessage::create(['conversation_id'=>$conversation->id,'channel'=>'sms','status'=>'received','body'=>$body?:'[Empty text message]','client_token'=>(string)Str::uuid()]);
   $delivery=TwilioDelivery::create(['inbox_message_id'=>$message->id,'inbox_conversation_id'=>$conversation->id,'kind'=>'sms','direction'=>'inbound','provider_sid'=>$data['MessageSid'],'from_number'=>$from,'to_number'=>$this->phone($data['To']),'status'=>'received']);
   $changes=['last_message_at'=>now(),'read_at'=>null];$choice=strtoupper(trim($body));
   if(($data['OptOutType']??'')==='STOP'||in_array($choice,['STOP','STOPALL','UNSUBSCRIBE','CANCEL','END','QUIT']))$changes['sms_dnd']=true;
   elseif(($data['OptOutType']??'')==='START'||in_array($choice,['START','UNSTOP']))$changes['sms_dnd']=false;
   $conversation->update($changes);return $delivery;
  });
 }
 public function incomingCall(array $data):TwilioDelivery {
  return DB::transaction(function()use($data){
   if($existing=TwilioDelivery::where('provider_sid',$data['CallSid'])->first())return $existing;
   $from=$this->phone($data['From']);$c=$this->incomingConversation($from);$s=TwilioSetting::findOrFail(1);
   $message=InboxMessage::create(['conversation_id'=>$c->id,'channel'=>'call','status'=>'ringing','body'=>'Incoming call from '.$from,'client_token'=>(string)Str::uuid()]);
   $row=TwilioDelivery::create(['inbox_message_id'=>$message->id,'inbox_conversation_id'=>$c->id,'kind'=>'call','direction'=>'inbound','provider_sid'=>$data['CallSid'],'from_number'=>$from,'to_number'=>$this->phone($data['To']),'agent_number'=>$s->voice_agent_number,'status'=>'ringing']);$c->update(['last_message_at'=>now(),'read_at'=>null]);return $row;
  });
 }
 public function dial(TwilioDelivery $row):string {
  $s=TwilioSetting::findOrFail(1);$voice=new VoiceResponse();$dial=$voice->dial('', ['callerId'=>$s->from_number,'timeout'=>25,'answerOnBridge'=>true]);
  $dial->number($row->direction==='inbound'?$row->agent_number:$row->to_number,['statusCallback'=>self::publicUrl('/webhooks/twilio/status/'.$row->id.'?leg=customer'),'statusCallbackMethod'=>'POST','statusCallbackEvent'=>'initiated ringing answered completed']);
  return (string)$voice;
 }
 public function status(TwilioDelivery $row,array $data,string $leg=''):void {
  DB::transaction(function()use($row,$data,$leg){
   $row=TwilioDelivery::lockForUpdate()->findOrFail($row->id);$sid=$data[$row->kind==='sms'?'MessageSid':'CallSid']??'';
   $status=$data[$row->kind==='sms'?'MessageStatus':'CallStatus']??'';
   $allowed=$row->kind==='sms'?['accepted','queued','sending','sent','delivered','undelivered','failed','read']:['queued','initiated','ringing','in-progress','completed','busy','failed','no-answer','canceled'];abort_unless(in_array($status,$allowed,true),422);
   $values=[];
   if($row->kind==='call'&&$leg==='customer'){
    abort_if($row->customer_call_sid&&$row->customer_call_sid!==$sid,403);if(!empty($data['ParentCallSid'])&&$row->provider_sid)abort_unless($data['ParentCallSid']===$row->provider_sid,403);
    $values+=['customer_call_sid'=>$sid,'customer_status'=>$status];
   }else{abort_if($row->provider_sid&&$row->provider_sid!==$sid,403);$values['provider_sid']=$sid;if($row->kind==='call')$values['agent_status']=$status;}
   $terminal=['delivered','read','undelivered','failed','completed','busy','no-answer','canceled'];
   $previous=$row->kind==='call'?($leg==='customer'?$row->customer_status:$row->agent_status):$row->status;
   if(in_array($previous,$terminal,true)&&!in_array($status,$terminal,true))return;
   if($row->kind==='sms'||$leg==='customer'||!$row->customer_call_sid)$values['status']=$row->kind==='call'&&$leg!=='customer'?'agent-'.$status:$status;
   if(isset($data['CallDuration']))$values['duration']=min(86400,max(0,(int)$data['CallDuration']));
   if(!empty($data['ErrorCode'])){$values['error_code']=(int)$data['ErrorCode'];$values['last_error']='Twilio reported error '.(int)$data['ErrorCode'].'. Check this event in Twilio Console.';}
   $row->update($values);$row->message?->update(['status'=>$row->status]);
  });
 }
}
