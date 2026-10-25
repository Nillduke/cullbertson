<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage,DB};
use App\Models\{Page,FormDefinition,TeamMember,ContactEnquiry,User};
class SupportPagesTest extends TestCase {
 use RefreshDatabase;
 protected $seed=true;protected $seeder=\Database\Seeders\ContentSeeder::class;
 public function test_all_requested_pages_have_the_new_design_at_their_original_paths():void {
  $designs=json_decode(file_get_contents(database_path('import/support-pages.json')),true);
  foreach($designs as $slug=>$sections){$response=$this->get('/'.$slug.'/')->assertOk()->assertSee('support-pages.css')->assertDontSee('localhost:8082')->assertDontSee('Continue on the original form');$this->assertSame(1,substr_count($response->getContent(),'<h1'));$this->assertDatabaseHas('pages',['slug'=>$slug]);}
  $this->get('/thanks-for-contacting-us/thanks-auto-insurance-quote/')->assertSee('Thanks for Requesting a Car Insurance Quote!')->assertSee('one business day');
  $this->get('/site-map/')->assertOk()->assertSee('Everything, in one place.')->assertSee('data-sp-search="sitemap"',false)->assertSee('/wp-sitemap.xml');
 }
 public function test_content_and_dynamic_directories_follow_database_edits_without_reseeding():void {
  $p=Page::where('slug','about-the-culbertson-agency')->firstOrFail();$section=$p->sections()->where('component','design_html')->first();$section->update(['content_html'=>str_replace('Lasting relationships.','Database-edited heading.',$section->content_html)]);$this->get('/'.$p->slug.'/')->assertOk()->assertSee('Database-edited heading.');
  $member=TeamMember::firstOrFail();$member->update(['name'=>'Database Person']);$this->get('/'.$p->slug.'/')->assertSee('Database Person');
  $insurance=Page::where('type','insurance')->firstOrFail();$copy=$insurance->insurance_content;$copy['breadcrumb']='Database Coverage';$insurance->update(['insurance_content'=>$copy]);$this->get('/insurance/')->assertSee('Database Coverage');$insurance->update(['status'=>'draft']);$this->get('/insurance/')->assertDontSee('Database Coverage');
  $count=$p->sections()->count();(new \Database\Seeders\SupportPageSeeder)->run();$this->assertSame($count,$p->sections()->count());$this->get('/'.$p->slug.'/')->assertSee('Database-edited heading.');
 }
 public function test_claims_and_policy_content_are_preserved_and_local_downloads_exist():void {
  $this->get('/claims/')->assertOk()->assertSee('Allstate Claims')->assertSee('800-547-8676')->assertSee('Universal North America');
  $this->get('/privacy-policy-2/')->assertOk()->assertSee('Information Sharing and Disclosure')->assertSee('Aaspire Agency DBA Culbertson Agency')->assertSee('policy-2');
  foreach(['culbertson_agency_logo.zip','Brian_culbertson_photo.zip'] as $file)$this->assertFileExists(public_path('assets/downloads/'.$file));
 }
 public function test_documents_are_private_and_admin_can_download_received_files():void {
  Storage::fake('local');$file=UploadedFile::fake()->create('requested-document.pdf',50,'application/pdf');$this->post('/requests/documents',$this->data()+['files'=>[$file]])->assertRedirect('/securely-send-pictures-documents-to-us/#service-request')->assertSessionHas('support_reference');
  $entry=ContactEnquiry::firstOrFail();$attachment=DB::table('contact_files')->first();$this->assertSame($entry->id,$attachment->contact_enquiry_id);Storage::disk('local')->assertExists($attachment->path);$this->assertStringStartsWith('enquiry-files/',$attachment->path);
  $this->get('/admin/enquiry-files/'.$attachment->id)->assertRedirect('/admin/login');$admin=User::factory()->create();$admin->forceFill(['is_admin'=>true])->save();$this->actingAs($admin)->get('/admin/inbox/contacts/'.$entry->id)->assertOk()->assertSee('requested-document.pdf');$this->get('/admin/enquiry-files/'.$attachment->id)->assertOk()->assertHeader('X-Content-Type-Options','nosniff')->assertDownload('requested-document.pdf');
 }
 public function test_invalid_files_missing_permission_and_honeypot_are_rejected():void {
  Storage::fake('local');$this->post('/requests/documents',$this->data())->assertSessionHasErrors('files');$this->post('/requests/documents',$this->data()+['files'=>[UploadedFile::fake()->create('danger.php',5,'text/plain')]])->assertSessionHasErrors('files.0');
  $data=$this->data();unset($data['consent']);$this->post('/requests/careers',$data)->assertSessionHasErrors('consent');$this->post('/requests/careers',array_replace($this->data(),['company_website'=>'bot']))->assertSessionHasErrors('company_website');$this->assertDatabaseCount('contact_enquiries',0);
 }
 public function test_careers_and_collector_enquiries_reach_the_existing_admin_inbox():void {
  foreach(['careers','classic'] as $kind)$this->post('/requests/'.$kind,$this->data())->assertRedirect()->assertSessionHas('support_reference');$this->assertDatabaseCount('contact_enquiries',2);
 }
 private function data():array{return ['name'=>'Synthetic Designer','email'=>'design-test@example.com','phone'=>'2025550100','message'=>'This is a synthetic test request for the local page redesign.','consent'=>'1'];}
}
