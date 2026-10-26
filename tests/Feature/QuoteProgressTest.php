<?php
namespace Tests\Feature;
use App\Models\{QuoteRequest,User,FormDefinition};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class QuoteProgressTest extends TestCase {
 use RefreshDatabase;
 protected $seed=true;
 protected $seeder=\Database\Seeders\ContentSeeder::class;
 protected function setUp():void {parent::setUp();$this->withCredentials();$this->withSession([]);$this->withCookie(config('session.cookie'),session()->getId());}
 private function base(string $service='auto'):array{return ['flow_id'=>(string)Str::uuid(),'service'=>$service,'step'=>'email','step_label'=>'Email address','percent'=>5];}
 private function fullAuto():array{return ['vehicles'=>[['year'=>'2024','make'=>'Honda','model'=>'Civic']],'drivers'=>[['month'=>'01','day'=>'15','year'=>'1990','gender'=>'Male','married'=>'No','accidents'=>'No','dui'=>'No','firstName'=>'Synthetic','lastName'=>'Progress']],'insured'=>'No','homeowner'=>'No','address'=>['street'=>'123 Example Street','unit'=>'','city'=>'Fort Myers','state'=>'FL','zip'=>'33907'],'email'=>'quote-progress@example.com','phone'=>'2025550100','consent'=>true,'sms'=>false];}
 private function fullService(array $service):array {
  $answers=[];foreach($service['questions'] as $q){if($q['type']==='choice')$answers[$q['id']]=$q['options'][0];else foreach($q['fields'] as $f)$answers[$q['id']][$f['id']]=($f['type']??'')==='number'?(string)min($f['max'],max($f['min'],in_array($f['id'],['year','yearBuilt'])?2020:500)):'Example';}
  return array_merge($answers,['insured'=>'No','start'=>'Just exploring','name'=>['firstName'=>'Synthetic','lastName'=>'Service'],'address'=>['street'=>'123 Example Street','unit'=>'','city'=>'Fort Myers','state'=>'FL','zip'=>'33907'],'email'=>['email'=>'quote-progress@example.com'],'phone'=>['phone'=>'2025550100'],'consent'=>true,'sms'=>false]);
 }
 public function test_each_continue_updates_one_partial_auto_lead_and_completion_is_idempotent():void {
  $base=$this->base();$response=$this->postJson('/quote/progress',$base+['request'=>['email'=>'quote-progress@example.com']])->assertOk();$reference=$response->json('reference');
  $quote=QuoteRequest::where('reference',$reference)->firstOrFail();$this->assertSame('incomplete',$quote->completion_status);$this->assertSame(5,$quote->progress_percent);$this->assertNull($quote->completed_at);
  $phone=array_replace($base,['step'=>'phone:0:0','step_label'=>'Phone number','percent'=>10,'request'=>['email'=>'quote-progress@example.com','phone'=>'(202) 555-0100','consent'=>true,'sms'=>false]]);
  $this->postJson('/quote/progress',$phone)->assertOk()->assertJson(['reference'=>$reference]);$this->assertDatabaseCount('quote_requests',1);$this->assertSame('2025550100',$quote->fresh()->payload['request']['phone']);
  $this->getJson('/quote/progress?'.http_build_query(['flow_id'=>$base['flow_id'],'service'=>'auto']))->assertOk()->assertJsonPath('draft.reference',$reference)->assertJsonPath('draft.payload.request.email','quote-progress@example.com')->assertJsonMissingPath('draft.capture_key');
  $quote->update(['status'=>'reviewed']);
  $final=['flow_id'=>$base['flow_id'],'request'=>$this->fullAuto()];$this->postJson('/auto-quote/submit',$final)->assertCreated()->assertJson(['reference'=>$reference]);$this->postJson('/auto-quote/submit',$final)->assertCreated()->assertJson(['reference'=>$reference]);
  $this->assertDatabaseCount('quote_requests',1);$quote->refresh();$this->assertSame('completed',$quote->completion_status);$this->assertSame('reviewed',$quote->status);$this->assertSame(100,$quote->progress_percent);$this->assertNotNull($quote->completed_at);
 }
 public function test_all_service_flows_capture_email_phone_and_details_before_final_submission():void {
  $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
  foreach(FormDefinition::data('services')['services'] as $key=>$service){
   $base=$this->base($key);$response=$this->postJson('/quote/progress',$base+['answers'=>['email'=>['email'=>'quote-progress@example.com']]])->assertOk();$reference=$response->json('reference');
   $full=$this->fullService($service);$this->postJson('/quote/progress',array_replace($base,['step'=>'phone','step_label'=>'Phone number','percent'=>12,'answers'=>['email'=>$full['email'],'phone'=>$full['phone'],'consent'=>true,'sms'=>false]]))->assertOk()->assertJson(['reference'=>$reference]);
   $this->postJson('/quote/progress',array_replace($base,['step'=>'details','step_label'=>'Service details','percent'=>80,'answers'=>$full]))->assertOk();
   $result=$this->postJson('/quote/submit',['flow_id'=>$base['flow_id'],'service'=>$key,'answers'=>$full])->assertCreated()->assertJson(['reference'=>$reference]);$this->assertStringContainsString('/quote/thank-you?receipt=',$result->json('redirect'));$this->assertSame('completed',QuoteRequest::where('reference',$reference)->firstOrFail()->completion_status);
  }
 }
 public function test_invalid_contacts_unknown_fields_and_unconsented_phone_steps_are_rejected():void {
  $base=$this->base();$this->postJson('/quote/progress',$base+['request'=>['email'=>'bad']])->assertUnprocessable();
  $this->postJson('/quote/progress',array_replace($base,['step'=>'phone:0:0','request'=>['email'=>'quote-progress@example.com','phone'=>'2025550100','consent'=>false]]))->assertUnprocessable();
  $this->postJson('/quote/progress',$base+['request'=>['email'=>'quote-progress@example.com','status'=>'contacted']])->assertUnprocessable();$this->assertDatabaseCount('quote_requests',0);
 }
 public function test_drafts_are_session_bound_and_visible_as_partial_leads_only_to_admins():void {
  $base=$this->base();$this->postJson('/quote/progress',$base+['request'=>['email'=>'quote-progress@example.com']])->assertOk();$quote=QuoteRequest::firstOrFail();
  $originalId=session()->getId();session()->migrate(true);$this->withCookie(config('session.cookie'),session()->getId());
  $this->getJson('/quote/progress?'.http_build_query(['flow_id'=>$base['flow_id'],'service'=>'auto']))->assertOk()->assertJsonPath('draft',null);
  session()->setId($originalId);$this->withCookie(config('session.cookie'),$originalId);$this->get('/admin/inbox/quotes/'.$quote->id)->assertRedirect('/admin/login');
  $admin=User::factory()->create();$admin->forceFill(['is_admin'=>true])->save();$this->actingAs($admin)->get('/admin/inbox?completion=incomplete')->assertOk()->assertSee($quote->reference)->assertSee('Partial leads');
  $this->get('/admin/inbox/quotes/'.$quote->id)->assertOk()->assertSee('Incomplete quote')->assertSee('quote-progress@example.com');
  $this->get('/admin/inbox?completion=completed')->assertOk()->assertDontSee($quote->reference);
 }
}
