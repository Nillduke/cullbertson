<?php
namespace App\Services;
use App\Models\TwilioSetting;
use Twilio\Rest\Client;
use Twilio\Http\CurlClient;
class TwilioGateway {
 private function client(TwilioSetting $s):Client{$client=new Client($s->account_sid,$s->auth_token);$client->setHttpClient(new CurlClient([CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5]));return $client;}
 public function check(TwilioSetting $s):array{$client=$this->client($s);$account=$client->api->v2010->accounts($s->account_sid)->fetch();return ['status'=>$account->status,'type'=>$account->type];}
 public function sms(TwilioSetting $s,string $to,string $body,string $callback):array {
  $options=['body'=>$body,'statusCallback'=>$callback];if($s->messaging_service_sid)$options['messagingServiceSid']=$s->messaging_service_sid;else $options['from']=$s->from_number;
  $message=$this->client($s)->messages->create($to,$options);return ['sid'=>$message->sid,'status'=>$message->status,'from'=>$message->from];
 }
 public function testSms(TwilioSetting $s,string $to):array {
  $options=['body'=>'Culbertson Agency Twilio test: SMS setup is working.'];if($s->messaging_service_sid)$options['messagingServiceSid']=$s->messaging_service_sid;else $options['from']=$s->from_number;
  $message=$this->client($s)->messages->create($to,$options);return ['sid'=>$message->sid,'status'=>$message->status,'from'=>$message->from];
 }
 public function testCall(TwilioSetting $s,string $to):array {
  $voice=new \Twilio\TwiML\VoiceResponse();$voice->say('This is a test call from Culbertson Agency. Your Twilio voice setup is working.');
  $call=$this->client($s)->calls->create($to,$s->from_number,['twiml'=>(string)$voice,'timeout'=>25]);return ['sid'=>$call->sid,'status'=>$call->status];
 }
 public function call(TwilioSetting $s,string $twiml,string $callback):array {
  $call=$this->client($s)->calls->create($s->voice_agent_number,$s->from_number,['twiml'=>$twiml,'statusCallback'=>$callback,'statusCallbackMethod'=>'POST','statusCallbackEvent'=>['initiated','ringing','answered','completed'],'timeout'=>25]);return ['sid'=>$call->sid,'status'=>$call->status];
 }
 public static function error(\Throwable $error):string {
  $code=(int)$error->getCode();return match($code){20003=>'Twilio authentication failed. Check the Account SID and Auth Token.',21608=>'Twilio trial accounts can send only to verified recipient numbers.',21610=>'This recipient has opted out of SMS. They must opt in again before receiving texts.',21211,21614=>'Twilio rejected the destination phone number.',21606=>'The sender number is not enabled for this messaging service.',30034=>'The US sender needs approved messaging registration in Twilio.',default=>'Twilio could not complete the request'.($code?' (code '.$code.')':'').'. Check the Twilio console before retrying.'};
 }
}
