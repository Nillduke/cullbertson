<?php
namespace App\Http\Controllers;
use App\Models\{TwilioSetting,TwilioDelivery};
use App\Services\TwilioCommunications;
use Illuminate\Http\Request;
use Twilio\TwiML\VoiceResponse;
class TwilioWebhookController extends Controller {
 private function xml(string $body){return response($body,200,['Content-Type'=>'text/xml; charset=UTF-8','Cache-Control'=>'no-store']);}
 public function sms(Request $request,TwilioCommunications $communications){
  abort_unless(TwilioSetting::findOrFail(1)->sms_enabled,503);
  $data=$request->validate(['MessageSid'=>'required|regex:/^[SM]M[a-fA-F0-9]{32}$/','From'=>'required|regex:/^\+[1-9][0-9]{7,14}$/','To'=>'required|regex:/^\+[1-9][0-9]{7,14}$/','Body'=>'nullable|string|max:1600','NumMedia'=>'nullable|integer|between:0,20','OptOutType'=>'nullable|in:STOP,START,HELP']);
  $communications->receiveSms($data);return $this->xml('<?xml version="1.0" encoding="UTF-8"?><Response/>');
 }
 public function voice(Request $request,TwilioCommunications $communications){
  $s=TwilioSetting::findOrFail(1);if(!$s->voice_enabled||!$s->voice_agent_number){$voice=new VoiceResponse();$voice->reject(['reason'=>'busy']);return $this->xml((string)$voice);}
  $data=$request->validate(['CallSid'=>'required|regex:/^CA[a-fA-F0-9]{32}$/','From'=>'required|regex:/^\+[1-9][0-9]{7,14}$/','To'=>'required|regex:/^\+[1-9][0-9]{7,14}$/']);
  return $this->xml($communications->dial($communications->incomingCall($data)));
 }
 public function connect(Request $request,TwilioDelivery $delivery,TwilioCommunications $communications){
  abort_unless($delivery->kind==='call'&&$delivery->direction==='outbound',404);$s=TwilioSetting::findOrFail(1);
  $sid=$request->validate(['CallSid'=>'required|regex:/^CA[a-fA-F0-9]{32}$/'])['CallSid'];abort_if($delivery->provider_sid&&$delivery->provider_sid!==$sid,403);
  if(!$delivery->provider_sid)$delivery->update(['provider_sid'=>$sid]);
  if($request->input('Digits')!=='1'||!$s->voice_enabled||$delivery->conversation->voice_dnd){$voice=new VoiceResponse();$voice->hangup();return $this->xml((string)$voice);}
  return $this->xml($communications->dial($delivery));
 }
 public function status(Request $request,TwilioDelivery $delivery,TwilioCommunications $communications){
  $data=$request->validate(['MessageSid'=>'nullable|regex:/^[SM]M[a-fA-F0-9]{32}$/','CallSid'=>'nullable|regex:/^CA[a-fA-F0-9]{32}$/','ParentCallSid'=>'nullable|regex:/^CA[a-fA-F0-9]{32}$/','MessageStatus'=>'nullable|string|max:30','CallStatus'=>'nullable|string|max:30','CallDuration'=>'nullable|integer|between:0,86400','ErrorCode'=>'nullable|integer']);
  abort_unless(!empty($data[$delivery->kind==='sms'?'MessageSid':'CallSid']),422);$leg=$request->query('leg','');abort_unless(in_array($leg,['','agent','customer']),422);
  $communications->status($delivery,$data,$leg);return response('',204);
 }
}
