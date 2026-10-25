<?php
namespace Tests\Feature;
use App\Models\{User,QuoteRequest,ContactEnquiry,FeedbackEntry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SubmissionInboxTest extends TestCase
{
 use RefreshDatabase;
 protected $seed=true;
 protected $seeder=\Database\Seeders\ContentSeeder::class;
 private function admin(){ $user=User::factory()->create();$user->forceFill(['is_admin'=>true])->save();return $user; }
 private function entries(){
  $quote=QuoteRequest::create(['reference'=>'TEST-INBOX-QUOTE','service'=>'home','payload'=>['serviceName'=>'Homeowners','answers'=>['name'=>['firstName'=>'Test','lastName'=>'Quote'],'email'=>['email'=>'inbox-quote@example.com'],'phone'=>['phone'=>'2395550100'],'property'=>['yearBuilt'=>'2002','squareFeet'=>'1800'],'consent'=>true]]]);
  $contact=ContactEnquiry::create(['reference'=>'TEST-INBOX-CONTACT','status'=>'reviewed','payload'=>['name'=>'Test Contact','email'=>'inbox-contact@example.com','phone'=>'2395550101','topic'=>'policy','message'=>'An enquiry with details to review.']]);
  $feedback=FeedbackEntry::create(['reference'=>'TEST-INBOX-FEEDBACK','payload'=>['type'=>'issue','message'=>'<script>alert(1)</script>','rating'=>4]]);
  return compact('quote','contact','feedback');
 }
 public function test_inbox_combines_and_filters_real_submissions():void {
  $records=$this->entries();$this->actingAs($this->admin());
  $this->get('/admin/inbox')->assertOk()->assertSee('TEST-INBOX-QUOTE')->assertSee('TEST-INBOX-CONTACT')->assertSee('TEST-INBOX-FEEDBACK')->assertSee('inbox-quote@example.com')->assertSee('/admin/inbox/quotes/'.$records['quote']->id,false);
  $this->get('/admin/inbox?kind=contacts&status=reviewed')->assertOk()->assertSee('TEST-INBOX-CONTACT')->assertDontSee('TEST-INBOX-QUOTE');
  $this->get('/admin/inbox?q=inbox-quote')->assertOk()->assertSee('TEST-INBOX-QUOTE')->assertDontSee('TEST-INBOX-CONTACT');
  $this->get('/admin/inbox?service=home')->assertOk()->assertSee('TEST-INBOX-QUOTE')->assertDontSee('TEST-INBOX-CONTACT');
  $this->get('/admin/inbox?to=2000-01-01')->assertOk()->assertSee('No matching submissions');
  $this->get('/admin/inbox?from=2026-10-25&to=2020-01-01')->assertSessionHasErrors('to');
 }
 public function test_details_show_all_answers_without_marking_read_or_executing_html():void {
  $records=$this->entries();$this->actingAs($this->admin());
  $this->get('/admin/inbox/quotes/'.$records['quote']->id)->assertOk()->assertSee('Test Quote')->assertSee('2002')->assertSee('1800')->assertSee('Permissions &amp; preferences',false)->assertHeader('Cache-Control','no-store, private');
  $this->assertSame('new',$records['quote']->fresh()->status);
  $this->get('/admin/inbox/contacts/'.$records['contact']->id)->assertOk()->assertSee('An enquiry with details to review.');
  $this->get('/admin/inbox/feedback/'.$records['feedback']->id)->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false)->assertSee('Anonymous visitor');
  $auto=QuoteRequest::create(['reference'=>'TEST-AUTO-DETAIL','service'=>'auto','payload'=>['request'=>['drivers'=>[['firstName'=>'Auto','lastName'=>'Driver','dateOfBirth'=>'1990-01-01']],'vehicles'=>[['year'=>'2024','make'=>'Honda','model'=>'Civic']],'email'=>'auto-test@example.com']]]);
  $this->get('/admin/inbox/quotes/'.$auto->id)->assertOk()->assertSee('Auto Driver')->assertSee('Civic')->assertSee('1990-01-01');
 }
 public function test_details_and_status_changes_are_admin_only():void {
  $records=$this->entries();$quote=$records['quote'];$url='/admin/inbox/quotes/'.$quote->id;
  $this->get($url)->assertRedirect('/admin/login');$this->post($url.'/status',['status'=>'reviewed'])->assertRedirect('/admin/login');
  $this->actingAs(User::factory()->create())->get($url)->assertForbidden();$this->post($url.'/status',['status'=>'reviewed'])->assertForbidden();
  $this->actingAs($this->admin());$this->post($url.'/status',['status'=>'contacted'])->assertRedirect($url);$this->assertSame('contacted',$quote->fresh()->status);
  $feedback=$records['feedback'];$this->post('/admin/inbox/feedback/'.$feedback->id.'/status',['status'=>'resolved'])->assertRedirect();$this->assertSame('resolved',$feedback->fresh()->status);
  $this->post($url.'/status',['status'=>'resolved'])->assertSessionHasErrors('status');$this->get('/admin/inbox/unknown/'.$quote->id)->assertNotFound();$this->get('/admin/inbox/quotes/999999')->assertNotFound();
 }
}
