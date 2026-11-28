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
 public function test(Request $request,TwilioGateway $gateway,TwilioCommunications $communications){
  $data=$request->validate(['channel'=>'required|in:sms,voice','test_phone'=>'required|string|max:24']);$to=$communications->phone($data['test_phone']);
  if(!$to)return back()->withErrors(['test_phone'=>'Enter a valid phone number, including country code if outside the US.']);
  $s=TwilioSetting::current();if(!$s->enabled||!$s->credentialsReady())return back()->withErrors(['twilio_test'=>'Save valid Twilio credentials and enable the connection first.']);
  if($data['channel']==='sms'&&(!$s->sms_enabled||(!$s->from_number&&!$s->messaging_service_sid)))return back()->withErrors(['twilio_test'=>'Enable SMS and configure a Twilio sender or Messaging Service first.']);
  if($data['channel']==='voice'&&(!$s->voice_enabled||!$s->from_number))return back()->withErrors(['twilio_test'=>'Enable voice and configure a Twilio phone number first.']);
  try{
   if($data['channel']==='sms'){$result=$gateway->testSms($s,$to);$message='Test SMS queued to '.$to.'. Twilio status: '.$result['status'].' ('.$result['sid'].').';}
   else{$result=$gateway->testCall($s,$to);$message='Test voice call started to '.$to.'. Twilio status: '.$result['status'].' ('.$result['sid'].').';}
   return back()->with('twilio_test_result',$message);
  }catch(\Throwable $error){return back()->with('twilio_error',TwilioGateway::error($error));}
 }
}
