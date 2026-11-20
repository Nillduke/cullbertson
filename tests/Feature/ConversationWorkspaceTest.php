<?php
namespace Tests\Feature;

use App\Models\{User,QuoteRequest,ContactEnquiry,InboxConversation,InboxMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversationWorkspaceTest extends TestCase {
 use RefreshDatabase;
 protected $seed=true;protected $seeder=\Database\Seeders\ContentSeeder::class;
 private function lead(string $reference='INBOX-WORKSPACE'):QuoteRequest{return QuoteRequest::withoutEvents(fn()=>QuoteRequest::create(['reference'=>$reference,'service'=>'home','status'=>'new','payload'=>['answers'=>['name'=>['firstName'=>'Local','lastName'=>'Visitor'],'email'=>'visitor@example.com']]]));}
 private function admin():User{$user=User::factory()->create(['is_admin'=>true]);$this->actingAs($user);return $user;}
 public function test_team_inbox_read_star_owner_and_original_submission_are_independent():void{
  $lead=$this->lead();$admin=$this->admin();$url='/admin/inbox/quotes/'.$lead->id;
  $this->get($url)->assertOk()->assertSee('Contact details')->assertSee('Reply draft')->assertSee('Local Visitor');
  $conversation=InboxConversation::firstOrFail();$this->assertNotNull($conversation->read_at);$this->assertSame('new',$lead->fresh()->status);
  $this->post($url.'/manage',['starred'=>1,'assigned_to'=>$admin->id])->assertRedirect($url);$this->assertTrue($conversation->fresh()->starred);$this->assertSame($admin->id,$conversation->fresh()->assigned_to);
  $this->get('/admin/inbox?view=starred')->assertSee($lead->reference);
  $this->post($url.'/manage',['read'=>'unread'])->assertRedirect('/admin/inbox?view=unread');$this->assertNull($conversation->fresh()->read_at);
  $this->get($url.'/thread')->assertOk()->assertJsonStructure(['html','version']);$this->assertNull($conversation->fresh()->read_at);
  $nonAdmin=User::factory()->create(['is_admin'=>false]);$this->post($url.'/manage',['assigned_to'=>$nonAdmin->id])->assertSessionHasErrors('assigned_to');
  $this->get($url.'/details')->assertOk()->assertSee('Submission record');
 }
 public function test_notes_and_replies_persist_without_sending_and_duplicate_posts_are_idempotent():void{
  Mail::fake();$lead=$this->lead();$this->admin();$url='/admin/inbox/quotes/'.$lead->id;
  $note=['channel'=>'note','body'=>'<script>alert(1)</script> Team follow-up','client_token'=>(string)Str::uuid()];
  $this->post($url.'/messages',$note)->assertSessionHas('success');$this->post($url.'/messages',$note)->assertSessionHas('success');$this->assertDatabaseCount('inbox_messages',1);
  $this->get($url)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
  $draft=['channel'=>'reply','body'=>'Thank you for your enquiry.','client_token'=>(string)Str::uuid()];
  $this->post($url.'/messages',$draft)->assertSessionHas('success');$message=InboxMessage::where('channel','reply')->firstOrFail();$this->assertSame('draft',$message->status);
  $this->post($url.'/messages',array_replace($draft,['message_id'=>$message->id,'body'=>'Updated saved reply.','client_token'=>(string)Str::uuid()]))->assertSessionHas('success');$this->assertDatabaseCount('inbox_messages',2);$this->assertSame('Updated saved reply.',$message->fresh()->body);
  $other=$this->lead('INBOX-OTHER');$this->post('/admin/inbox/quotes/'.$other->id.'/messages',array_replace($draft,['message_id'=>$message->id]))->assertNotFound();
  $this->get($url.'/thread')->assertOk()->assertSee('Updated saved reply.')->assertSee('Not sent');Mail::assertNothingSent();
 }
 public function test_conversation_endpoints_require_admin_and_validate_source_and_content():void{
  $lead=$this->lead();$url='/admin/inbox/quotes/'.$lead->id;
  $this->post($url.'/messages',[])->assertRedirect('/admin/login');$this->get($url.'/thread')->assertRedirect('/admin/login');
  $this->actingAs(User::factory()->create(['is_admin'=>false]))->post($url.'/manage',['starred'=>1])->assertForbidden();
  $this->admin();$this->post($url.'/messages',['channel'=>'email','body'=>'Invalid','client_token'=>(string)Str::uuid()])->assertSessionHasErrors('channel');
  $this->post($url.'/messages',['channel'=>'note','body'=>'','client_token'=>'invalid'])->assertSessionHasErrors(['body','client_token']);
  $this->get('/admin/inbox/contacts/999999/thread')->assertNotFound();$this->assertDatabaseCount('inbox_messages',0);
 }
}
