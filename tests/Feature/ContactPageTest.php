<?php
namespace Tests\Feature;
use App\Models\{ContactEnquiry,FormDefinition,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ContactPageTest extends TestCase {
    use RefreshDatabase;
    protected $seed=true;
    protected $seeder=\Database\Seeders\ContentSeeder::class;
    private function message():array {return ['name'=>'Synthetic contact test','email'=>'contact-test@example.com','phone'=>'','topic'=>'policy','message'=>'Synthetic enquiry for local automated validation.'];}
    public function test_contact_design_and_content_are_database_driven():void {
        $this->get('/contact-us/')->assertOk()->assertViewIs('content.contact')->assertSee('role="tablist"',false)->assertSee('action="/contact-us/send"',false)->assertSee('(239) 210-7700')->assertDontSee('Continue on the original form');
        $form=FormDefinition::where('key','contact-page')->first();$copy=$form->definition;$copy['heading']='Database contact heading';$copy['tabs'][0]['cards'][0]['heading']='Database support option';$copy['labels']['message']='Database message label';$copy['topics'][]=['value'=>'database-topic','label'=>'Database topic option'];$form->update(['definition'=>$copy]);
        $this->get('/contact-us/')->assertSee('Database contact heading')->assertSee('Database support option')->assertSee('Database message label')->assertSee('Database topic option');
        $this->post('/contact-us/send',array_replace($this->message(),['topic'=>'database-topic']))->assertSessionHasNoErrors()->assertRedirect('/contact-us/#contact-form');
    }
    public function test_messages_are_saved_privately_and_visible_to_admin_with_status_controls():void {
        $response=$this->post('/contact-us/send',$this->message())->assertSessionHasNoErrors()->assertRedirect('/contact-us/#contact-form')->assertSessionHas('contact_reference');
        $entry=ContactEnquiry::firstOrFail();$this->assertSame('contact-test@example.com',$entry->payload['email']);$this->assertSame('new',$entry->status);
        $this->get('/admin/inbox')->assertRedirect('/admin/login');$this->get('/contact-us/')->assertDontSee('contact-test@example.com');
        $admin=User::factory()->create();$admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin)->get('/admin/inbox')->assertOk()->assertSee('Contact enquiries')->assertSee($entry->reference)->assertSee('contact-test@example.com');
        $this->post('/admin/contacts/'.$entry->id.'/status',['status'=>'contacted'])->assertSessionHasNoErrors();$this->assertSame('contacted',$entry->fresh()->status);
    }
    public function test_invalid_or_bot_messages_are_not_saved():void {
        $this->from('/contact-us/')->post('/contact-us/send',array_replace($this->message(),['email'=>'not-an-email','message'=>'tiny','topic'=>'unknown']))->assertSessionHasErrors(['email','message','topic']);
        $this->post('/contact-us/send',array_replace($this->message(),['company_website'=>'bot.example']))->assertSessionHasErrors('company_website');
        $this->post('/contact-us/send',array_replace($this->message(),['message'=>str_repeat('x',3001)]))->assertSessionHasErrors('message');
        $this->assertDatabaseCount('contact_enquiries',0);
    }
    public function test_message_status_changes_require_an_administrator():void {
        $entry=ContactEnquiry::create(['reference'=>'TEST-PRIVATE','payload'=>$this->message()]);
        $this->post('/admin/contacts/'.$entry->id.'/status',['status'=>'contacted'])->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->post('/admin/contacts/'.$entry->id.'/status',['status'=>'contacted'])->assertForbidden();
        $this->assertSame('new',$entry->fresh()->status);
    }
}
