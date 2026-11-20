<?php
namespace App\Http\Middleware;
use App\Models\TwilioSetting;
use App\Services\TwilioCommunications;
use Closure;
use Illuminate\Http\Request;
use Twilio\Security\RequestValidator;
class VerifyTwilioSignature {
 public function handle(Request $request,Closure $next){
  $s=TwilioSetting::find(1);abort_unless($s?->enabled&&$s->credentialsReady(),503);
  $signature=$request->header('X-Twilio-Signature','');$url=TwilioCommunications::publicUrl('/'.$request->path()).($request->getQueryString()?'?'.$request->getQueryString():'');
  abort_unless($request->input('AccountSid')===$s->account_sid&&(new RequestValidator($s->auth_token))->validate($signature,$url,$request->request->all()),403);
  return $next($request);
 }
}
