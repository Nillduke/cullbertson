<?php
namespace App\Http\Controllers;
use App\Models\{SlackSetting,SlackDelivery};
use App\Services\{LeadSlack,SlackTransport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SlackSettingsController extends Controller {
 public function index(){
  $settings=SlackSetting::current();$deliveries=SlackDelivery::latest()->paginate(15);
  $stats=collect(['sent','failed','pending'])->mapWithKeys(fn($status)=>[$status=>SlackDelivery::where('status',$status)->count()])->all();
  return response()->view('admin.slack',compact('settings','deliveries','stats'))->header('Cache-Control','private, no-store');
 }
 public function save(Request $request){
  $s=SlackSetting::current();$data=$request->only(['channel','website_url','webhook_url']);
  foreach(['enabled','notify_quotes','notify_partials','notify_contacts','notify_feedback','include_contact','clear_webhook'] as $key)$data[$key]=$request->boolean($key);
  $data['webhook_url']=trim((string)($data['webhook_url']??''));$data['channel']=trim((string)($data['channel']??''));
  $v=Validator::make($data,['channel'=>'nullable|string|max:100|not_regex:/[\r\n]/','website_url'=>'required|url:http,https|max:500','webhook_url'=>'nullable|string|max:1000','enabled'=>'boolean','notify_quotes'=>'boolean','notify_partials'=>'boolean','notify_contacts'=>'boolean','notify_feedback'=>'boolean','include_contact'=>'boolean','clear_webhook'=>'boolean']);
  $v->after(function($v)use($data,$s){
   if($data['webhook_url']!==''&&!SlackTransport::validWebhook($data['webhook_url']))$v->errors()->add('webhook_url','Enter a valid https://hooks.slack.com/services/... incoming webhook URL.');
   if($data['enabled']&&$data['channel']==='')$v->errors()->add('channel','Enter the Slack channel label.');
   if($data['enabled']&&$data['webhook_url']===''&&(!$s->getRawOriginal('webhook_url')||$data['clear_webhook']))$v->errors()->add('webhook_url','Add a webhook URL before enabling notifications.');
  });
  if($v->fails())return back()->withErrors($v)->withInput($request->except(['webhook_url','_token']));
  $values=$v->validated();$url=$values['webhook_url'];$clear=$values['clear_webhook'];unset($values['webhook_url'],$values['clear_webhook']);$values['website_url']=rtrim($values['website_url'],'/');$values['updated_by']=$request->user()->id;
  if($url!==''||$clear||$values['channel']!==$s->channel){$values['last_test_at']=null;$values['last_test_status']=null;$values['last_test_message']=null;}
  if($url!=='')$values['webhook_url']=$url;elseif($clear)$values['webhook_url']=null;
  $s->fill($values)->save();return redirect(url('/admin/integrations/slack'))->with('success','Slack settings saved. Only new matching submissions will create notifications.');
 }
 public function test(LeadSlack $slack){
  $s=SlackSetting::current();if(!$s->configured())return back()->with('slack_error','Save a channel label, webhook URL and website URL before testing.');
  try{$row=$slack->test();$ok=$row->status==='sent';$message=$ok?'Slack accepted the test message. Check the channel associated with your webhook.':$row->last_error;}
  catch(\Throwable){$ok=false;$message='The saved webhook could not be read. Re-enter it in Slack settings.';}
  $s->update(['last_test_at'=>now(),'last_test_status'=>$ok?'sent':'failed','last_test_message'=>$message]);
  return redirect(url('/admin/integrations/slack').'#slack-test')->with($ok?'success':'slack_error',$message);
 }
 public function retry(SlackDelivery $delivery,LeadSlack $slack){
  abort_unless($delivery->status==='failed',422,'Only failed notifications can be retried.');$s=SlackSetting::current();
  if(!$s->enabled||!$s->configured())return back()->with('slack_error','Enable complete Slack settings before retrying.');
  $delivery->update(['status'=>'pending','attempts'=>0,'locked_at'=>null,'next_attempt_at'=>null,'webhook_url'=>$s->webhook_url,'channel'=>$s->channel]);
  $row=$slack->deliver($delivery->id);return back()->with($row->status==='sent'?'success':'slack_error',$row->status==='sent'?'Slack accepted the notification.':($row->last_error?:'Notification remains pending.'));
 }
}
