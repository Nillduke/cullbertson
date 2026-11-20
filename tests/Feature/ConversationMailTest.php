<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Models\{User,QuoteRequest,InboxMessage,MailSetting,MailDelivery};
use App\Services\{AdminMailTransport,LeadMail};
use App\Mail\ConversationReply;

class ConversationMailTest extends TestCase {
 use RefreshDatabase;
 protected $seed=true;protected $seeder=\Database\Seeders\ContentSeeder::class;
 private function setupMail():string {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  MailSetting::current()->update(['enabled'=>true,'provider'=>'godaddy_local','host'=>'localhost','port'=>25,'encryption'=>'none','authentication'=>false,'from_address'=>'sender@example.com','from_name'=>'Agency','recipients'=>['team@example.com']]);
  $lead=QuoteRequest::withoutEvents(fn()=>QuoteRequest::create(['reference'=>'REPLY-TEST','service'=>'auto','payload'=>['request'=>['email'=>'client@example.com','drivers'=>[['firstName'=>'Client','lastName'=>'Person']]]]]));
  return '/admin/inbox/quotes/'.$lead->id;
 }
 private function input(array $extra=[]):array{return array_merge(['channel'=>'reply','body'=>'Hello, your <script>question</script> is received.','client_token'=>(string)Str::uuid(),'delivery_action'=>'send'],$extra);}
 public function test_explicit_send_uses_existing_smtp_and_customer_recipient_once():void {
  $url=$this->setupMail();
  $this->mock(AdminMailTransport::class,function($mock){$mock->shouldReceive('send')->once()->withArgs(function($settings,$to,$mail){
   $this->assertSame('sender@example.com',$settings->from_address);$this->assertSame(['client@example.com'],$to);$this->assertInstanceOf(ConversationReply::class,$mail);
   $html=$mail->render();$this->assertStringContainsString('Client Person',$html);$this->assertStringContainsString('&lt;script&gt;',$html);$this->assertStringNotContainsString('<script>',$html);$this->assertStringNotContainsString('/admin/',$html);return true;
  })->andReturn('smtp-accepted@example.com');});
  $input=$this->input();$this->post($url.'/messages',$input)->assertRedirect($url)->assertSessionHas('success');
  $this->post($url.'/messages',$input)->assertRedirect($url);
  $this->assertDatabaseCount('inbox_messages',1);$this->assertDatabaseCount('mail_deliveries',1);$this->assertSame('accepted',InboxMessage::firstOrFail()->status);
  $row=MailDelivery::firstOrFail();$this->assertSame('accepted',$row->status);$this->assertSame(1,$row->attempts);$this->assertSame(['client@example.com'],$row->recipients);
  $this->get($url.'/thread')->assertOk()->assertSee('Email accepted by SMTP')->assertSee('client@example.com')->assertDontSee('Edit draft');
 }
 public function test_smtp_failure_is_visible_and_existing_outbox_retry_updates_the_thread():void {
  $url=$this->setupMail();
  $this->mock(AdminMailTransport::class,fn($mock)=>$mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP authentication failed SECRET')));
  $this->post($url.'/messages',$this->input())->assertSessionHas('mail_error');
  $row=MailDelivery::firstOrFail();$this->assertSame('failed',$row->status);$this->assertNotNull($row->next_attempt_at);$this->assertSame('failed',InboxMessage::firstOrFail()->status);$this->assertStringNotContainsString('SECRET',$row->last_error);
  $this->get($url.'/thread')->assertSee('Email failed')->assertSee('View delivery history and retry');
  $this->mock(AdminMailTransport::class,fn($mock)=>$mock->shouldReceive('send')->once()->andReturn('retried@example.com'));
  app(LeadMail::class)->deliver($row->id);$this->assertSame('accepted',$row->fresh()->status);$this->assertSame('accepted',InboxMessage::firstOrFail()->status);
 }
 public function test_configured_smtp_does_not_send_notes_or_saved_drafts():void {
  $url=$this->setupMail();$this->mock(AdminMailTransport::class,fn($mock)=>$mock->shouldNotReceive('send'));
  $this->post($url.'/messages',$this->input(['delivery_action'=>'draft']))->assertSessionHas('success');
  $this->post($url.'/messages',$this->input(['channel'=>'note','delivery_action'=>'draft']))->assertSessionHas('success');
  $this->assertDatabaseCount('mail_deliveries',0);$this->assertDatabaseHas('inbox_messages',['status'=>'draft']);$this->assertDatabaseHas('inbox_messages',['status'=>'internal']);
  $this->post($url.'/messages',$this->input(['channel'=>'note']))->assertStatus(422);
 }
 public function test_existing_draft_can_be_sent_but_accepted_messages_cannot_be_edited():void {
  $url=$this->setupMail();$this->post($url.'/messages',$this->input(['delivery_action'=>'draft']));$draft=InboxMessage::firstOrFail();
  $this->mock(AdminMailTransport::class,fn($mock)=>$mock->shouldReceive('send')->once()->andReturn('draft-send@example.com'));
  $this->post($url.'/messages',$this->input(['message_id'=>$draft->id,'body'=>'Ready to send']))->assertSessionHas('success');
  $this->assertDatabaseCount('inbox_messages',1);$this->assertSame('accepted',$draft->fresh()->status);
  $this->post($url.'/messages',$this->input(['message_id'=>$draft->id,'delivery_action'=>'draft']))->assertNotFound();
 }
 public function test_disabled_log_or_missing_recipient_blocks_customer_send_without_creating_delivery():void {
  $url=$this->setupMail();$this->mock(AdminMailTransport::class,fn($mock)=>$mock->shouldNotReceive('send'));
  foreach([['enabled'=>false],['enabled'=>true,'provider'=>'log']] as $values){MailSetting::current()->update($values);$this->post($url.'/messages',$this->input())->assertSessionHasErrors('body');}
  MailSetting::current()->update(['provider'=>'godaddy_local']);QuoteRequest::withoutEvents(fn()=>QuoteRequest::firstOrFail()->update(['payload'=>['request'=>[]]]));
  $this->post($url.'/messages',$this->input())->assertSessionHasErrors('body');$this->assertDatabaseCount('mail_deliveries',0);$this->assertDatabaseCount('inbox_messages',0);
 }
}
