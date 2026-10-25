<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Page,User,FormDefinition};
class InsuranceTemplateTest extends TestCase {
    use RefreshDatabase;
    protected $seed=true;
    protected $seeder=\Database\Seeders\ContentSeeder::class;
    public function test_all_insurance_pages_use_one_template_and_service_specific_database_content():void {
        $pages=Page::where('type','insurance')->get();$this->assertCount(11,$pages);
        foreach($pages as $page){
            $c=$page->insurance_content;
            $response=$this->get('/'.$page->slug)->assertOk()->assertViewIs('content.insurance')->assertSee('data-template="shared-insurance"',false)->assertSee($c['heading'])->assertSee($c['quote_url'],false)->assertSee('insurance-detail.css',false);
            foreach(['hero','feature_image'] as $key)$this->assertFileExists(public_path($c[$key]));
            foreach($c['coverages'] as $row)$response->assertSee($row['title']);
            foreach($c['questions'] as $row)$response->assertSee($row['title']);
            $this->get($c['quote_url'])->assertSuccessful();
        }
    }
    private function payload(array $content):array {return ['title'=>'New insurance from database','slug'=>'insurance/new-service','type'=>'insurance','status'=>'published','article_category'=>'Auto','insurance'=>$content,'feature_points'=>implode("\n",$content['feature_points']),'resources_json'=>json_encode($content['resources'])];}
    public function test_new_insurance_page_inherits_layout_and_all_fields_are_editable():void {
        $user=User::factory()->create();$user->forceFill(['is_admin'=>true])->save();$this->actingAs($user);
        $this->get('/admin/pages/create?type=insurance')->assertOk()->assertSee('Shared insurance page design')->assertSee('name="insurance[heading]"',false);
        $c=Page::where('type','insurance')->first()->insurance_content;$c['heading']='New dynamic coverage';$c['questions'][]=['title'=>'Database FAQ proof','body'=>'<p>Editable answer proof.</p>'];
        $this->post('/admin/pages',$this->payload($c))->assertSessionHasNoErrors()->assertRedirect();
        $page=Page::where('slug','insurance/new-service')->firstOrFail();$this->assertCount(0,$page->sections);
        $this->get('/'.$page->slug)->assertOk()->assertViewIs('content.insurance')->assertSee('New dynamic coverage')->assertSee('Database FAQ proof')->assertSee('Editable answer proof.')->assertSee('insurance-quote-band')->assertSee('insurance-articles');
        $c['heading']='Updated dynamic coverage';$c['coverages'][0]['body']='<p>Changed coverage proof</p>';$c['phone']='555 dynamic phone';$c['quote_button']='Request dynamic quote';$c['resources'][0]['title']='Updated resource proof';
        $this->post('/admin/pages/'.$page->id,$this->payload($c))->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/'.$page->slug)->assertSee('Updated dynamic coverage')->assertSee('Changed coverage proof')->assertSee('555 dynamic phone')->assertSee('Request dynamic quote')->assertSee('Updated resource proof')->assertDontSee('New dynamic coverage');
        $payload=$this->payload($c);$payload['status']='draft';$this->post('/admin/pages/'.$page->id,$payload)->assertSessionHasNoErrors();$this->get('/'.$page->slug)->assertNotFound();
    }
    public function test_published_insurance_requires_content_and_rejects_unsafe_links():void {
        $user=User::factory()->create();$user->forceFill(['is_admin'=>true])->save();$this->actingAs($user);
        $c=Page::where('type','insurance')->first()->insurance_content;$c['hero']='javascript:bad';$c['quote_url']='javascript:bad';
        $this->post('/admin/pages',$this->payload($c))->assertSessionHasErrors(['insurance.hero','insurance.quote_url']);
        $c=Page::where('type','insurance')->first()->insurance_content;$payload=$this->payload($c);unset($payload['insurance']['heading']);$this->post('/admin/pages',$payload)->assertSessionHasErrors('insurance.heading');
    }
}
