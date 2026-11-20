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
 public function test_partial_quotes_show_saved_contact_answers_and_form_context_in_the_thread():void{
  $lead=QuoteRequest::withoutEvents(fn()=>QuoteRequest::create(['reference'=>'PARTIAL-DETAILS','service'=>'auto','completion_status'=>'incomplete','progress_percent'=>6,'last_step_label'=>'Phone number','payload'=>['request'=>['email'=>'partial@example.com','phone'=>'2395550100','drivers'=>[['firstName'=>'','lastName'=>'']],'vehicles'=>[['year'=>'2024','make'=>'Honda','model'=>'Civic']]],'sourcePage'=>['url'=>'https://example.com/auto-quote','title'=>'Your auto quote page']]]));
  $this->admin();$url='/admin/inbox/quotes/'.$lead->id;
  $response=$this->get($url)->assertOk()->assertSee('partial@example.com')->assertSee('Not entered yet')->assertSee('Phone number')->assertSee('Form / request')->assertSee('Your auto quote page');
  $item=$response->viewData('item');$this->assertSame('partial@example.com',$item['display_name']);$this->assertFalse($item['name_provided']);
  $this->get($url.'/thread')->assertOk()->assertSee('2395550100')->assertSee('Civic')->assertSee('6% complete')->assertSee('Only the answers saved so far');
 }
 public function test_imported_fields_and_original_page_are_visible_in_thread():void{
  $lead=ContactEnquiry::withoutEvents(fn()=>ContactEnquiry::create(['reference'=>'IMPORTED-CONTEXT','payload'=>['name'=>'Original Person','email'=>'original@example.com','message'=>'Full original customer message.','wordpress'=>['form_name'=>'Original contact form','source'=>'Gravity Forms','entry_id'=>12,'original_status'=>'active','fields'=>['Preferred coverage'=>'Boat coverage'],'entry'=>['source_url'=>'https://culbertsonagency.com/contact-us/']]] ]));
  $this->admin();$url='/admin/inbox/contacts/'.$lead->id;
  $this->get($url.'/thread')->assertOk()->assertSee('Original contact form')->assertSee('Full original customer message.')->assertSee('Boat coverage')->assertSee('contact-us');
 }
 public function test_source_capture_drops_external_referrers_and_tracking_query_parameters():void{
  $source=app(\App\Services\SubmissionSource::class);
  $request=\Illuminate\Http\Request::create('https://culbertsonagency.com/quote/progress','POST');
  $request->headers->set('referer','https://external.example/steal');$this->assertSame([],$source->capture($request));
  $request->headers->set('referer','https://culbertsonagency.com/quote/?service=boat&token=private-value&utm_source=campaign');
  $context=$source->capture($request)['sourcePage'];$this->assertStringEndsWith('/quote/?service=boat',$context['url']);$this->assertStringNotContainsString('private-value',$context['url']);
 }
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
