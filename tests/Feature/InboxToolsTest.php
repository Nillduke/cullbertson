<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Models\{User,QuoteRequest,InboxConversation,InboxMessage,MailSetting,MailDelivery};
use App\Services\{AdminMailTransport,ConversationMail,SubmissionPresentation};

class InboxToolsTest extends TestCase {
 use RefreshDatabase;
 protected $seed=true;protected $seeder=\Database\Seeders\ContentSeeder::class;
 private function lead(string $ref):QuoteRequest{return QuoteRequest::withoutEvents(fn()=>QuoteRequest::create(['reference'=>$ref,'service'=>'auto','payload'=>['request'=>['email'=>strtolower($ref).'@example.com']]]));}
 private function admin():User{$user=User::factory()->create(['is_admin'=>true]);$this->actingAs($user);return $user;}
 public function test_bulk_actions_are_validated_atomic_and_reversible():void {
  $admin=$this->admin();$a=$this->lead('BULK-A');$b=$this->lead('BULK-B');$selected=['quotes:'.$a->id,'quotes:'.$b->id];
  $this->post('/admin/inbox/bulk',['selected'=>$selected,'action'=>'star'])->assertSessionHas('success');$this->assertSame(2,InboxConversation::where('starred',true)->count());
  $this->post('/admin/inbox/bulk',['selected'=>$selected,'action'=>'assign','assigned_to'=>$admin->id])->assertRedirect('/admin/inbox');$this->assertSame(2,InboxConversation::where('assigned_to',$admin->id)->count());
  $this->post('/admin/inbox/bulk',['selected'=>$selected,'action'=>'archive'])->assertSessionHas('success');$this->assertSame('trash',$a->fresh()->status);
  $this->assertSame(0,$this->get('/admin/inbox')->viewData('entries')->total());$this->assertSame(2,$this->get('/admin/inbox?view=archived')->viewData('entries')->total());
  $this->post('/admin/inbox/bulk',['selected'=>$selected,'action'=>'restore']);$this->assertSame('new',$a->fresh()->status);
  $this->post('/admin/inbox/bulk',['selected'=>['quotes:'.$a->id,'quotes:999999'],'action'=>'archive'])->assertNotFound();$this->assertSame('new',$a->fresh()->status);
  $this->post('/admin/inbox/bulk',['selected'=>$selected,'action'=>'delete'])->assertSessionHasErrors('action');$this->assertDatabaseCount('quote_requests',2);
 }
 public function test_tags_followers_and_owner_filters_persist_without_changing_original_fields():void {
  $admin=$this->admin();$a=$this->lead('TAG-A');$this->lead('TAG-B');$url='/admin/inbox/quotes/'.$a->id;$payload=$a->payload;
  $this->post($url.'/manage',['tags'=>'New lead, Auto coverage','followers'=>[$admin->id],'assigned_to'=>$admin->id])->assertRedirect($url);
  $conversation=InboxConversation::where('source_id',$a->id)->firstOrFail();$this->assertSame(2,$conversation->tags()->count());$this->assertSame(1,$conversation->followers()->count());$this->assertSame($payload,$a->fresh()->payload);
  $tag=$conversation->tags()->firstOrFail();$this->get('/admin/inbox?tag='.$tag->id)->assertSee('TAG-A')->assertDontSee('TAG-B');
  $this->get('/admin/inbox?view=following')->assertSee('TAG-A')->assertDontSee('TAG-B');$this->get('/admin/inbox?view=mine')->assertSee('TAG-A')->assertDontSee('TAG-B');
  $this->post($url.'/manage',['tags'=>'','followers'=>null])->assertRedirect($url);$this->assertSame(0,$conversation->tags()->count());$this->assertSame(0,$conversation->followers()->count());
  $normal=User::factory()->create(['is_admin'=>false]);$this->post($url.'/manage',['followers'=>[$normal->id]])->assertSessionHasErrors('followers.0');
 }
 public function test_message_search_sort_and_tool_pages_are_real_and_do_not_mark_leads_read():void {
  $this->admin();$a=$this->lead('SEARCH-A');$b=$this->lead('SEARCH-B');$a->update(['created_at'=>now()->subDay()]);
  $conversation=InboxConversation::create(['kind'=>'quotes','source_id'=>$a->id]);InboxMessage::create(['conversation_id'=>$conversation->id,'channel'=>'reply','status'=>'draft','body'=>'Distinctive follow-up phrase','client_token'=>(string)Str::uuid()]);
  $this->get('/admin/inbox?q=Distinctive')->assertSee('SEARCH-A')->assertDontSee('SEARCH-B');$conversation->update(['read_at'=>null]);
  foreach(['actions'=>'Manual actions','analytics'=>'Conversations at a glance','settings'=>'Inbox settings'] as $section=>$label)$this->get('/admin/inbox?section='.$section)->assertOk()->assertSee($label);
  $this->assertNull($conversation->fresh()->read_at);$sorted=$this->get('/admin/inbox?sort=received_asc')->assertOk()->viewData('entries')->pluck('reference')->all();$this->assertSame(['SEARCH-A','SEARCH-B'],$sorted);
  $this->get('/admin/inbox?view=drafts')->assertSee('SEARCH-A')->assertDontSee('SEARCH-B');
 }
 public function test_do_not_contact_blocks_new_and_already_queued_customer_email():void {
  $this->admin();$lead=$this->lead('DND');$url='/admin/inbox/quotes/'.$lead->id;
  MailSetting::current()->update(['enabled'=>true,'provider'=>'godaddy_local','from_address'=>'sender@example.com','from_name'=>'Agency','authentication'=>false,'recipients'=>['team@example.com']]);
  $conversation=InboxConversation::create(['kind'=>'quotes','source_id'=>$lead->id]);$message=InboxMessage::create(['conversation_id'=>$conversation->id,'channel'=>'reply','status'=>'draft','body'=>'Saved reply','client_token'=>(string)Str::uuid()]);
  $mail=app(ConversationMail::class);$delivery=$mail->enqueue($message,app(SubmissionPresentation::class)->summarize($lead,'quotes'));
  $this->post($url.'/manage',['email_dnd'=>1])->assertRedirect($url);$this->mock(AdminMailTransport::class,fn($mock)=>$mock->shouldNotReceive('send'));
  $this->post($url.'/messages',['channel'=>'reply','body'=>'Must not send','client_token'=>(string)Str::uuid(),'delivery_action'=>'send'])->assertSessionHasErrors('body');
  $this->assertSame('blocked',$mail->deliver($delivery->id)->status);$this->assertSame('blocked',$message->fresh()->status);$this->assertNull($delivery->fresh()->next_attempt_at);
  $this->post($url.'/manage',['email_dnd'=>0]);$this->assertTrue($mail->readiness(app(SubmissionPresentation::class)->summarize($lead,'quotes'))['ready']);
 }
 public function test_bulk_and_management_endpoints_require_admin_access():void {
  $lead=$this->lead('PROTECTED');$this->post('/admin/inbox/bulk',[])->assertRedirect('/admin/login');
  $this->actingAs(User::factory()->create(['is_admin'=>false]))->post('/admin/inbox/bulk',['selected'=>['quotes:'.$lead->id],'action'=>'star'])->assertForbidden();
 }
}
