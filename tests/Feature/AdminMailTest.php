<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\{User,MailSetting,MailDelivery,QuoteRequest,ContactEnquiry};
use App\Services\{AdminMailTransport,LeadMail};
use App\Mail\AgencyNotification;

class AdminMailTest extends TestCase {
 use RefreshDatabase;
 protected $seed=true;protected $seeder=\Database\Seeders\ContentSeeder::class;
 private function admin():User{return User::factory()->create(['is_admin'=>true]);}
 private function settings(array $extra=[]):MailSetting {
  $s=MailSetting::current();$s->fill(array_merge(['enabled'=>true,'provider'=>'smtp','host'=>'smtp.example.com','port'=>587,'encryption'=>'starttls','authentication'=>true,'username'=>'site@example.com','password'=>'Synthetic-SMTP-Secret','from_address'=>'site@example.com','from_name'=>'Agency','recipients'=>['team@example.com'],'website_url'=>'https://agency.example','subject_prefix'=>'Agency','notify_quotes'=>true,'notify_partials'=>false,'notify_contacts'=>true,'notify_feedback'=>false,'include_contact'=>true,'timeout'=>10],$extra))->save();return $s;
 }
 private function input(array $extra=[]):array{return array_merge(['enabled'=>1,'provider'=>'smtp','host'=>'smtp.example.com','port'=>587,'encryption'=>'starttls','authentication'=>1,'username'=>'site@example.com','password'=>'Synthetic-SMTP-Secret','from_address'=>'site@example.com','from_name'=>'Agency','recipients'=>"team@example.com\nsecond@example.com",'website_url'=>'https://agency.example','subject_prefix'=>'Agency','notify_quotes'=>1,'notify_contacts'=>1,'include_contact'=>1,'timeout'=>10],$extra);}
 private function quote(array $extra=[]):QuoteRequest{return QuoteRequest::create(array_merge(['reference'=>'CUL-261026-'.strtoupper(bin2hex(random_bytes(6))),'service'=>'auto','completion_status'=>'completed','payload'=>['request'=>['email'=>'visitor@example.com','phone'=>'2025550100','drivers'=>[['firstName'=>'Synthetic','lastName'=>'Visitor','year'=>'1990']],'address'=>['street'=>'Private street']]]],$extra));}
 public function test_settings_are_admin_only_and_passwords_are_encrypted_and_never_reflected():void {
  $this->get('/admin/mail')->assertRedirect('/admin/login');
  $this->actingAs(User::factory()->create(['is_admin'=>false]))->get('/admin/mail')->assertForbidden();
  $this->actingAs($this->admin())->get('/admin/mail')->assertOk()->assertSee('GoDaddy cPanel relay')->assertSee('Delivery history');
  $this->post('/admin/mail',$this->input())->assertRedirect('/admin/mail');
  $s=MailSetting::current();$this->assertSame('Synthetic-SMTP-Secret',$s->password);$this->assertNotSame($s->password,$s->getRawOriginal('password'));
  $this->assertArrayNotHasKey('password',$s->toArray());
  $this->get('/admin/mail')->assertDontSee('Synthetic-SMTP-Secret')->assertHeader('Cache-Control','no-store, private');
  $this->post('/admin/mail',$this->input(['password'=>'']))->assertSessionHasNoErrors();$this->assertSame('Synthetic-SMTP-Secret',$s->fresh()->password);
  $this->post('/admin/mail',$this->input(['port'=>0]))->assertSessionHasErrors('port')->assertSessionMissing('_old_input.password');
 }
 public function test_godaddy_preset_enforces_relay_settings_and_rejects_gmail_sender():void {
  $this->actingAs($this->admin());$input=$this->input(['provider'=>'godaddy_local','host'=>'ignored.example.com','port'=>465,'encryption'=>'smtps','from_address'=>'site@agency.example']);
  $this->post('/admin/mail',$input)->assertSessionHasNoErrors();$s=MailSetting::current();
  $this->assertSame('localhost',$s->host);$this->assertSame(25,(int)$s->port);$this->assertFalse($s->authentication);$this->assertNull($s->getRawOriginal('password'));
  $config=app(AdminMailTransport::class)->configuration($s);$this->assertFalse($config['auto_tls']);$this->assertNull($config['username']);
  $this->post('/admin/mail',array_merge($input,['from_address'=>'example@gmail.com']))->assertSessionHasErrors('from_address');
 }
 public function test_notifications_are_once_per_completed_lead_and_partial_is_a_separate_opt_in():void {
  $s=$this->settings();$quote=$this->quote();$this->assertDatabaseCount('mail_deliveries',1);
  app(LeadMail::class)->enqueue($quote,'completed');$quote->update(['status'=>'reviewed']);$this->assertDatabaseCount('mail_deliveries',1);
  $partial=$this->quote(['completion_status'=>'incomplete']);$this->assertDatabaseCount('mail_deliveries',1);
  $partial->update(['completion_status'=>'completed']);$this->assertDatabaseCount('mail_deliveries',2);
  $s->update(['notify_partials'=>true]);$early=$this->quote(['completion_status'=>'incomplete']);$early->update(['progress_percent'=>30]);$this->assertDatabaseCount('mail_deliveries',3);
  $early->update(['completion_status'=>'completed']);$this->assertDatabaseCount('mail_deliveries',4);
  $this->assertSame(1,MailDelivery::where('source_id',$early->id)->where('event','partial')->count());
 }
 public function test_delivery_success_is_idempotent_and_mail_omits_sensitive_quote_answers():void {
  $this->settings();$this->quote();$row=MailDelivery::firstOrFail();
  $this->mock(AdminMailTransport::class,function($mock){$mock->shouldReceive('send')->once()->withArgs(function($settings,$recipients,$mail){
   $this->assertInstanceOf(AgencyNotification::class,$mail);$this->assertSame(['team@example.com'],$recipients);
   $html=$mail->render();$this->assertStringContainsString('visitor@example.com',$html);$this->assertStringNotContainsString('Private street',$html);$this->assertStringNotContainsString('1990',$html);$this->assertStringContainsString('https://agency.example/admin/inbox/quotes/',$html);return true;
  })->andReturn('test-message@example.com');});
  $sent=app(LeadMail::class)->deliver($row->id);$this->assertSame('accepted',$sent->status);
  app(LeadMail::class)->deliver($row->id);$this->assertSame(1,$sent->fresh()->attempts);
  $this->assertNotSame(json_encode($row->recipients),$row->getRawOriginal('recipients'));
 }
 public function test_mail_failure_keeps_lead_and_redacts_errors_and_supports_retry():void {
  $this->settings();$quote=$this->quote();$row=MailDelivery::firstOrFail();
  $this->mock(AdminMailTransport::class,fn($m)=>$m->shouldReceive('send')->once()->andThrow(new \RuntimeException('Authentication failed Synthetic-SMTP-Secret')));
  $failed=app(LeadMail::class)->deliver($row->id);$this->assertSame('failed',$failed->status);$this->assertNotNull($failed->next_attempt_at);$this->assertStringNotContainsString('Synthetic-SMTP-Secret',$failed->last_error);$this->assertDatabaseHas('quote_requests',['id'=>$quote->id]);
  $this->mock(AdminMailTransport::class,fn($m)=>$m->shouldReceive('send')->once()->andReturn('retry@example.com'));
  $this->actingAs($this->admin())->post('/admin/mail/retry/'.$row->id)->assertSessionHas('success');
  $this->assertSame('accepted',$row->fresh()->status);
  $this->post('/admin/mail/retry/'.$row->id)->assertStatus(422);
 }
 public function test_privacy_notices_are_minimal_and_rolled_back_submissions_do_not_leave_mail():void {
  $this->settings();
  DB::beginTransaction();$entry=ContactEnquiry::create(['reference'=>'ENQ-ROLLBACK','payload'=>['name'=>'Temporary','email'=>'temp@example.com','topic'=>'general']]);$this->assertDatabaseCount('mail_deliveries',1);DB::rollBack();$this->assertDatabaseCount('mail_deliveries',0);$this->assertDatabaseMissing('contact_enquiries',['id'=>$entry->id]);
  ContactEnquiry::create(['reference'=>'PRV-SYNTHETIC','payload'=>['name'=>'Private Name','email'=>'private@example.com','topic'=>'Privacy request','message'=>'Private deletion details']]);
  $this->mock(AdminMailTransport::class,function($m){$m->shouldReceive('send')->once()->withArgs(function($s,$to,$mail){$html=$mail->render();$this->assertStringNotContainsString('Private Name',$html);$this->assertStringNotContainsString('private@example.com',$html);$this->assertStringNotContainsString('Private deletion details',$html);return true;})->andReturn('minimal@example.com');});
  app(LeadMail::class)->deliver(MailDelivery::firstOrFail()->id);
 }
 public function test_connection_and_test_actions_show_real_results_without_exposing_credentials():void {
  $this->settings();$this->actingAs($this->admin());
  $this->mock(AdminMailTransport::class,fn($m)=>$m->shouldReceive('check')->once()->andThrow(new \RuntimeException('Authentication Synthetic-SMTP-Secret')));
  $this->post('/admin/mail/connection')->assertSessionHas('mail_error');$this->assertSame('failed',MailSetting::current()->connection_status);$this->assertStringNotContainsString('Synthetic-SMTP-Secret',MailSetting::current()->connection_message);
  $this->mock(AdminMailTransport::class,fn($m)=>$m->shouldReceive('send')->once()->andReturn('test@example.com'));
  $this->post('/admin/mail/test',['test_recipient'=>'test@example.com'])->assertSessionHas('success');
  $this->assertDatabaseHas('mail_deliveries',['kind'=>'test','status'=>'accepted']);
 }
 public function test_admin_asset_and_action_paths_work_under_the_deployment_folder():void {
  $request=\Illuminate\Http\Request::create('https://agency.example/culbertsonoct/admin/mail','GET',[],[],[],['SCRIPT_NAME'=>'/culbertsonoct/index.php','PHP_SELF'=>'/culbertsonoct/index.php','SCRIPT_FILENAME'=>'/home/site/public_html/culbertsonoct/index.php']);
  $this->assertSame('/culbertsonoct',$request->getBaseUrl());
  $response=(new \App\Http\Middleware\AdminPathLinks)->handle($request,fn()=>response('<a href="/admin/mail">Mail</a><script src="/cms.js"></script><form action="/admin/mail"></form><a href="https://outside.example/">Outside</a>',200,['Content-Type'=>'text/html']));
  $this->assertStringContainsString('href="/culbertsonoct/admin/mail"',$response->getContent());$this->assertStringContainsString('src="/culbertsonoct/cms.js"',$response->getContent());$this->assertStringContainsString('https://outside.example/',$response->getContent());
 }
 public function test_real_laravel_log_transport_does_not_claim_delivery():void {
  $this->settings(['provider'=>'log','authentication'=>false,'password'=>null]);$this->quote();
  $row=app(LeadMail::class)->deliver(MailDelivery::firstOrFail()->id);
  $this->assertSame('logged',$row->status);$this->assertNull($row->last_error);$this->assertNotNull($row->message_id);
 }
}
