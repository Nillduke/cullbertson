<?php
namespace App\Http\Controllers;
use App\Models\{MailSetting,MailDelivery};
use App\Services\{AdminMailTransport,LeadMail};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MailSettingsController extends Controller {
 public const PROVIDERS=['godaddy_local'=>'GoDaddy cPanel relay','godaddy_relay'=>'GoDaddy legacy / Plesk relay','smtp'=>'Custom SMTP','log'=>'Log only — no delivery'];
 public function index(){
  $settings=MailSetting::current();$deliveries=MailDelivery::latest()->paginate(15);
  $stats=collect(['accepted','failed','pending','logged'])->mapWithKeys(fn($status)=>[$status=>MailDelivery::where('status',$status)->count()])->all();
  return response()->view('admin.mail-settings',compact('settings','deliveries','stats')+['providers'=>self::PROVIDERS])->header('Cache-Control','private, no-store');
 }
 public function save(Request $request){
  $s=MailSetting::current();$data=$request->except(['_token','password']);
  $recipients=preg_split('/[\s,;]+/',trim((string)$request->input('recipients','')),-1,PREG_SPLIT_NO_EMPTY);
  $data['recipients']=array_values(array_unique($recipients));
  foreach(['enabled','authentication','notify_quotes','notify_partials','notify_contacts','notify_feedback','include_contact','clear_password'] as $key)$data[$key]=$request->boolean($key);
  $data['password']=$request->input('password');
  if(in_array($data['provider']??'',['godaddy_local','godaddy_relay'],true)){
   $data['host']=$data['provider']==='godaddy_local'?'localhost':'relay-hosting.secureserver.net';$data['port']=25;$data['encryption']='none';$data['authentication']=false;
  }
  $v=Validator::make($data,[
   'provider'=>['required',Rule::in(array_keys(self::PROVIDERS))],'host'=>'required|string|max:253|regex:/^[A-Za-z0-9][A-Za-z0-9.-]*$/',
   'port'=>'required|integer|between:1,65535','encryption'=>'required|in:none,starttls,smtps','authentication'=>'boolean',
   'username'=>['nullable',Rule::requiredIf($data['authentication']&&$data['enabled']),'string','max:255'],
   'password'=>'nullable|string|max:2048','from_address'=>['nullable',Rule::requiredIf($data['enabled']),'email:rfc','max:254'],
   'from_name'=>'required|string|max:120|not_regex:/[\r\n]/','reply_to'=>'nullable|email:rfc|max:254',
   'recipients'=>['array','max:10',Rule::requiredIf($data['enabled'])],'recipients.*'=>'required|email:rfc|max:254',
   'website_url'=>'required|url:http,https|max:500','subject_prefix'=>'required|string|max:100|not_regex:/[\r\n]/',
   'timeout'=>'required|integer|between:3,30','enabled'=>'boolean','notify_quotes'=>'boolean','notify_partials'=>'boolean',
   'notify_contacts'=>'boolean','notify_feedback'=>'boolean','include_contact'=>'boolean','clear_password'=>'boolean',
  ]);
  $v->after(function($v)use($data,$s){
   if($v->errors()->any())return;
   if($data['enabled']&&$data['authentication']&&(!$data['password']&&(!$s->getRawOriginal('password')||$data['clear_password'])))$v->errors()->add('password','Enter the SMTP password or keep the saved password.');
   if($data['authentication']&&($data['encryption']??'')==='none'&&!in_array($data['host']??'',['localhost','127.0.0.1'],true))$v->errors()->add('encryption','Use STARTTLS or implicit TLS for authenticated remote SMTP.');
   if(str_starts_with($data['provider']??'','godaddy')&&preg_match('/@(gmail\.com|googlemail\.com|yahoo\.com|outlook\.com|hotmail\.com|live\.com|aol\.com)$/i',$data['from_address']??''))$v->errors()->add('from_address','GoDaddy relay requires a sender mailbox on a domain you control. Gmail may be a recipient, not the relay sender.');
   if($data['enabled']&&!count($data['recipients']))$v->errors()->add('recipients','Enter at least one notification recipient.');
  });
  if($v->fails())return back()->withErrors($v)->withInput($request->except(['password','_token']));
  $values=$v->validated();$password=$values['password'];$clear=$values['clear_password'];unset($values['password'],$values['clear_password']);
  $values['website_url']=rtrim($values['website_url'],'/');
  $values['updated_by']=$request->user()->id;$values['connection_status']=null;$values['connection_message']=null;$values['connection_checked_at']=null;
  if(!$values['authentication']){$values['username']=null;$values['password']=null;}elseif(is_string($password)&&$password!=='')$values['password']=$password;elseif($clear)$values['password']=null;
  $s->fill($values)->save();
  return redirect(url('/admin/mail'))->with('success','Email settings saved. New submissions use these settings; existing leads are not emailed automatically.');
 }
 public function connection(AdminMailTransport $transport){
  $s=MailSetting::current();
  if($s->provider==='log')return back()->with('mail_error','Log mode has no SMTP connection. Choose a relay or SMTP provider.');
  if($s->authentication&&(!$s->username||!$s->getRawOriginal('password')))return back()->with('mail_error','Enter SMTP username and password before testing authentication.');
  try{$transport->check($s);$status='success';$message='SMTP connection and any configured authentication succeeded. No email was sent.';}
  catch(\Throwable $e){$status='failed';$message=AdminMailTransport::safeError($e);}
  $s->update(['connection_status'=>$status,'connection_message'=>$message,'connection_checked_at'=>now()]);
  return redirect(url('/admin/mail').'#mail-test')->with($status==='success'?'success':'mail_error',$message);
 }
 public function test(Request $request,LeadMail $mail){
  $data=$request->validate(['test_recipient'=>'required|email:rfc|max:254']);$s=MailSetting::current();
  if($s->missing(true))return back()->with('mail_error','Set '.implode(', ',$s->missing(true)).' before sending a test.');
  $row=$mail->test($data['test_recipient']);
  return redirect(url('/admin/mail').'#mail-history')->with(in_array($row->status,['accepted','logged'])?'success':'mail_error',$row->status==='accepted'?'The SMTP server accepted the test message. Check the recipient inbox and spam folder.':($row->status==='logged'?'Test logged only. No email was delivered.':$row->last_error));
 }
 public function retry(MailDelivery $delivery,LeadMail $mail){
  abort_unless($delivery->status==='failed',422,'Only failed notifications can be retried.');
  $s=MailSetting::current();if(!$s->enabled||$s->missing())return back()->with('mail_error','Enable complete email settings before retrying notifications.');
  $delivery->update(['status'=>'pending','attempts'=>0,'next_attempt_at'=>null,'locked_at'=>null]);
  $row=$mail->deliver($delivery->id);
  return back()->with(in_array($row->status,['accepted','logged'])?'success':'mail_error',$row->status==='accepted'?'The SMTP server accepted the retried notification.':($row->status==='logged'?'Notification logged only.':($row->last_error?:'Notification remains pending.')));
 }
}
