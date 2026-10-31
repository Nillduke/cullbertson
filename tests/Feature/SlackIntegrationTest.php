<?php
namespace Tests\Feature;
use App\Models\{User,SlackSetting,SlackDelivery,QuoteRequest,ContactEnquiry};
use App\Services\{LeadSlack,SlackTransport};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,DB};
use Tests\TestCase;

class SlackIntegrationTest extends TestCase {
 use RefreshDatabase;
 protected $seed=true;protected $seeder=\Database\Seeders\ContentSeeder::class;
 private const HOOK='https://hooks.slack.com/services/TEXAMPLE/BEXAMPLE/SyntheticSecretOnly';
 protected function setUp():void{parent::setUp();Http::preventStrayRequests();}
 private function settings(array $extra=[]):SlackSetting{$s=SlackSetting::current();$s->update(array_merge(['enabled'=>true,'channel'=>'#leads','webhook_url'=>self::HOOK,'website_url'=>'https://agency.example','notify_quotes'=>true,'notify_contacts'=>true,'include_contact'=>true],$extra));return $s;}
 private function quote(array $extra=[]):QuoteRequest{return QuoteRequest::create(array_merge(['reference'=>'CUL-261031-'.strtoupper(bin2hex(random_bytes(6))),'service'=>'auto','completion_status'=>'completed','payload'=>['request'=>['email'=>'person@example.com','phone'=>'2025550100','drivers'=>[['firstName'=>'<!channel>','lastName'=>'Visitor','year'=>'1990']],'address'=>['street'=>'Private street']]]],$extra));}
 public function test_admin_settings_encrypt_and_hide_webhooks_and_never_flash_secrets():void {
  $this->get('/admin/integrations/slack')->assertRedirect('/admin/login');
  $this->actingAs(User::factory()->create(['is_admin'=>false]))->get('/admin/integrations/slack')->assertForbidden();
  $this->actingAs(User::factory()->create(['is_admin'=>true]))->get('/admin/integrations/slack')->assertOk()->assertSee('Slack notifications');
  $input=['enabled'=>1,'channel'=>'#leads','webhook_url'=>self::HOOK,'website_url'=>'https://agency.example','notify_quotes'=>1];
  $this->post('/admin/integrations/slack',$input)->assertSessionHasNoErrors();$s=SlackSetting::current();
  $this->assertSame(self::HOOK,$s->webhook_url);$this->assertNotSame(self::HOOK,$s->getRawOriginal('webhook_url'));$this->assertArrayNotHasKey('webhook_url',$s->toArray());
  $this->get('/admin/integrations/slack')->assertDontSee('SyntheticSecretOnly')->assertHeader('Cache-Control','no-store, private');
  $this->post('/admin/integrations/slack',array_replace($input,['webhook_url'=>'']))->assertSessionHasNoErrors();$this->assertSame(self::HOOK,$s->fresh()->webhook_url);
  $this->post('/admin/integrations/slack',array_replace($input,['website_url'=>'bad']))->assertSessionHasErrors('website_url')->assertSessionMissing('_old_input.webhook_url');
  $this->post('/admin/integrations/slack',array_replace($input,['webhook_url'=>'https://example.com/services/secret']))->assertSessionHasErrors('webhook_url');
  $this->post('/admin/integrations/slack',array_replace($input,['webhook_url'=>'','clear_webhook'=>1]))->assertSessionHasErrors('webhook_url');
 }
 public function test_partial_and_completed_notifications_are_independent_and_deduplicated():void {
  $s=$this->settings();$partial=$this->quote(['completion_status'=>'incomplete']);$this->assertDatabaseCount('slack_deliveries',0);
  $partial->update(['completion_status'=>'completed']);$this->assertDatabaseCount('slack_deliveries',1);app(LeadSlack::class)->enqueue($partial,'completed');$this->assertDatabaseCount('slack_deliveries',1);
  $s->update(['notify_partials'=>true]);$early=$this->quote(['completion_status'=>'incomplete']);$early->update(['progress_percent'=>30]);$this->assertDatabaseCount('slack_deliveries',2);$early->update(['completion_status'=>'completed']);$this->assertDatabaseCount('slack_deliveries',3);
  DB::beginTransaction();$this->quote();DB::rollBack();$this->assertDatabaseCount('slack_deliveries',3);
  $s->update(['enabled'=>false]);$this->quote();$this->assertDatabaseCount('slack_deliveries',3);Http::assertNothingSent();
 }
 public function test_rate_limits_retry_without_losing_leads_and_payload_omits_full_answers():void {
  $this->settings();$quote=$this->quote();$row=SlackDelivery::firstOrFail();
  Http::fakeSequence()->push('rate_limited',429,['Retry-After'=>'120'])->push('ok',200);
  $failed=app(LeadSlack::class)->deliver($row->id);$this->assertSame('failed',$failed->status);$this->assertNotNull($failed->next_attempt_at);$this->assertDatabaseHas('quote_requests',['id'=>$quote->id]);
  $this->travel(3)->minutes();$this->assertSame(1,app(LeadSlack::class)->pending());$this->assertSame('sent',$row->fresh()->status);app(LeadSlack::class)->deliver($row->id);Http::assertSentCount(2);
  Http::assertSent(function($request){$data=$request->data();$json=json_encode($data,JSON_UNESCAPED_SLASHES);$this->assertStringContainsString('person@example.com',$json);$this->assertStringNotContainsString('Private street',$json);$this->assertStringNotContainsString('1990',$json);$this->assertArrayNotHasKey('channel',$data);$this->assertSame('plain_text',$data['blocks'][1]['fields'][0]['type']);$this->assertStringNotContainsString('/admin/inbox/',$json);$this->assertNotContains('actions',array_column($data['blocks'],'type'));return true;});
  $this->assertNotSame(self::HOOK,$row->getRawOriginal('webhook_url'));$this->assertArrayNotHasKey('webhook_url',$row->toArray());$this->travelBack();
 }
 public function test_privacy_and_document_messages_omit_personal_details():void {
  $this->settings();Http::fake(['hooks.slack.com/*'=>Http::response('ok',200)]);
  foreach([['PRV-TEST','Privacy request'],['ENQ-DOC','Document submission']] as [$reference,$topic])ContactEnquiry::create(['reference'=>$reference,'payload'=>['topic'=>$topic,'name'=>'Private Person','email'=>'private@example.com','message'=>'Private request details']]);
  foreach(SlackDelivery::all() as $row)app(LeadSlack::class)->deliver($row->id);
  Http::assertSent(function($request){$json=json_encode($request->data());$this->assertStringNotContainsString('Private Person',$json);$this->assertStringNotContainsString('private@example.com',$json);$this->assertStringNotContainsString('Private request details',$json);return true;});Http::assertSentCount(2);
 }
 public function test_admin_test_and_retry_use_saved_settings_without_enabling_notifications():void {
  $s=$this->settings(['enabled'=>false]);$this->actingAs(User::factory()->create(['is_admin'=>true]));Http::fakeSequence()->push('no_active_hooks',404)->push('ok',200);
  $this->post('/admin/integrations/slack/test')->assertSessionHas('slack_error');$row=SlackDelivery::firstOrFail();$this->assertNull($row->next_attempt_at);$this->assertSame('failed',$s->fresh()->last_test_status);
  $s->update(['enabled'=>true,'channel'=>'#updated','webhook_url'=>'https://hooks.slack.com/services/TNEW/BNEW/NewSyntheticSecret']);
  $this->post('/admin/integrations/slack/retry/'.$row->id)->assertSessionHas('success');$this->assertSame('sent',$row->fresh()->status);$this->assertSame('#updated',$row->fresh()->channel);
  $this->post('/admin/integrations/slack/retry/'.$row->id)->assertStatus(422);Http::assertSentCount(2);
 }
 public function test_transport_does_not_follow_redirects_or_expose_exception_urls():void {
  $transport=app(SlackTransport::class);$this->assertFalse($transport->send('http://127.0.0.1/secret',[])['ok']);Http::assertNothingSent();
  Http::fake(['hooks.slack.com/*'=>Http::response('redirect',302,['Location'=>'http://127.0.0.1/secret'])]);$result=$transport->send(self::HOOK,['text'=>'test']);$this->assertFalse($result['ok']);$this->assertFalse($result['retry']);Http::assertSentCount(1);
  Http::fake(fn()=>throw new \Illuminate\Http\Client\ConnectionException('Could not connect to '.self::HOOK));$result=$transport->send(self::HOOK,['text'=>'test']);$this->assertTrue($result['retry']);$this->assertStringNotContainsString('SyntheticSecretOnly',$result['error']);
 }
}
