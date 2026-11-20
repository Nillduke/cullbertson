<?php
namespace App\Http\Controllers;
use App\Models\{TwilioSetting,TwilioDelivery};
use App\Services\{TwilioCommunications,TwilioGateway};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class TwilioSettingsController extends Controller {
 public function index(){return response()->view('admin.twilio-settings',['settings'=>TwilioSetting::current(),'deliveries'=>TwilioDelivery::latest()->paginate(15),'webhooks'=>['Incoming SMS'=>TwilioCommunications::publicUrl('/webhooks/twilio/sms'),'Incoming voice'=>TwilioCommunications::publicUrl('/webhooks/twilio/voice')]])->header('Cache-Control','private, no-store');}
 public function save(Request $request,TwilioCommunications $communications){
  $s=TwilioSetting::current();$request->merge(['enabled'=>$request->boolean('enabled'),'sms_enabled'=>$request->boolean('sms_enabled'),'voice_enabled'=>$request->boolean('voice_enabled')]);
  foreach(['from_number','voice_agent_number'] as $key)if($request->filled($key))$request->merge([$key=>$communications->phone($request->input($key))?:$request->input($key)]);
  $data=$request->validate(['enabled'=>'boolean','sms_enabled'=>'boolean','voice_enabled'=>'boolean','account_sid'=>['nullable',Rule::requiredIf($request->boolean('enabled')),'regex:/^AC[a-fA-F0-9]{32}$/'],'auth_token'=>'nullable|string|min:20|max:2000|not_regex:/[\r\n]/','from_number'=>['nullable',Rule::requiredIf($request->boolean('enabled')&&($request->boolean('voice_enabled')||($request->boolean('sms_enabled')&&!$request->filled('messaging_service_sid')))),'regex:/^\+[1-9][0-9]{7,14}$/'],'messaging_service_sid'=>'nullable|regex:/^MG[a-fA-F0-9]{32}$/','voice_agent_number'=>['nullable',Rule::requiredIf($request->boolean('enabled')&&$request->boolean('voice_enabled')),'regex:/^\+[1-9][0-9]{7,14}$/','different:from_number'],'clear_token'=>'sometimes|boolean']);
  if($data['enabled']&&!$request->filled('auth_token')&&(!$s->getRawOriginal('auth_token')||$request->boolean('clear_token')))return back()->withErrors(['auth_token'=>'Enter the Twilio Auth Token before enabling.'])->withInput($request->except(['auth_token','_token']));
  $token=$data['auth_token']??null;unset($data['auth_token'],$data['clear_token']);if($token)$data['auth_token']=$token;elseif($request->boolean('clear_token'))$data['auth_token']=null;
  $s->fill($data+['connection_status'=>null,'connection_message'=>null,'connection_checked_at'=>null])->save();
  return redirect(url('/admin/integrations/twilio'))->with('success','Twilio settings saved. No messages or calls were sent.');
 }
 public function connection(TwilioGateway $gateway){
  $s=TwilioSetting::current();if(!$s->credentialsReady())return back()->withErrors(['connection'=>'Save your Account SID and Auth Token first.']);
  try{$account=$gateway->check($s);$status=$account['status']==='active'?'success':'failed';$message='Account status: '.$account['status'].'; type: '.$account['type'].'. No SMS or call was sent.';}catch(\Throwable $error){$status='failed';$message=TwilioGateway::error($error);}
  $s->update(['connection_status'=>$status,'connection_message'=>$message,'connection_checked_at'=>now()]);return redirect(url('/admin/integrations/twilio'))->with($status==='success'?'success':'twilio_error',$message);
 }
}
