<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;

class SlackTransport {
 public static function validWebhook(string $url):bool{return (bool)preg_match('~^https://hooks\.slack\.com/services/[A-Za-z0-9_-]+/[A-Za-z0-9_-]+/[A-Za-z0-9_-]+$~D',$url);}
 public function send(string $url,array $payload):array {
  if(!self::validWebhook($url))return ['ok'=>false,'error'=>'The saved Slack webhook URL is invalid. Enter a Slack incoming webhook URL.','retry'=>false];
  try{$r=Http::connectTimeout(4)->timeout(10)->withoutRedirecting()->asJson()->post($url,$payload);}
  catch(\Throwable){return ['ok'=>false,'error'=>'Could not complete the Slack request. Check the webhook and hosting HTTPS access.','retry'=>true];}
  if($r->status()===200&&trim($r->body())==='ok')return ['ok'=>true];
  $error=match(trim($r->body())){
   'channel_is_archived'=>'The Slack channel is archived. Create a webhook for an active channel.',
   'channel_not_found'=>'Slack could not find the channel. Check the webhook in Slack.',
   'no_active_hooks','no_service','invalid_token'=>'The Slack webhook was removed or revoked. Enter a new webhook URL.',
   'action_prohibited'=>'The Slack workspace policy does not allow this webhook.',
   'invalid_payload'=>'Slack rejected the message format.',
   default=>$r->status()===429?'Slack rate limit reached. This notification will be retried.':'Slack did not accept the message. Check the webhook and Slack app permissions.',
  };
  return ['ok'=>false,'error'=>$error,'retry'=>$r->status()===429||$r->serverError(),'retry_after'=>max(60,min(3600,(int)$r->header('Retry-After')))];
 }
}
