<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Page,SiteBlock,FormDefinition,User};
class DynamicCmsTest extends TestCase
{
    use RefreshDatabase;
    protected $seed=true;
    protected $seeder=\Database\Seeders\ContentSeeder::class;
    private function admin():User { $user=User::factory()->create();$user->forceFill(['is_admin'=>true])->save();return $user; }
    public function test_database_section_changes_are_visible_immediately():void{
        $page=Page::where('slug','insurance/golf-cart-insurance')->firstOrFail();
        $content=$page->insurance_content;$content['heading']='Database-only live headline';$page->update(['insurance_content'=>$content]);
        $this->get('/'.$page->slug)->assertOk()->assertSee('Database-only live headline');
        $page->update(['status'=>'draft']);$this->get('/'.$page->slug)->assertNotFound();
    }
    public function test_database_post_is_added_to_home_blog_and_insurance_feeds():void{
        $post=Page::create(['slug'=>'new-database-post','title'=>'Fresh database article','type'=>'post','status'=>'published','published_at'=>now()->subSecond(),'categories'=>['Auto'],'featured_image'=>'/assets/coverage/auto.webp','description'=>'New content from the CMS.']);
        $post->sections()->create(['name'=>'Article body','content_html'=>'<article class="imported-content shell"><p>New database article body.</p></article>']);
        $this->get('/new-database-post')->assertSee('New database article body.');
        $this->get('/')->assertSee('Fresh database article');
        $this->get('/expert-insurance-insights-and-tips-culbertson-agency-articles')->assertSee('Fresh database article');
        $this->get('/insurance/golf-cart-insurance')->assertSee('Fresh database article');
    }
    public function test_shared_content_and_form_options_come_from_database():void{
        SiteBlock::where('key','footer')->update(['content_html'=>'<footer id="site-footer">Database footer proof</footer>']);
        $this->get('/')->assertSee('Database footer proof');
        $form=FormDefinition::where('key','auto-options')->firstOrFail();$data=$form->definition;$data['makes'][]='Database make proof';$form->update(['definition'=>$data]);
        $this->getJson('/forms/auto-options')->assertOk()->assertJsonFragment(['Database make proof']);
    }
    public function test_cms_requires_an_administrator():void{
        $this->get('/admin')->assertRedirect('/admin/login');
        $user=User::factory()->create();$this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin')->assertOk()->assertSee('Pages & posts',false);
    }
    public function test_admin_can_create_publish_and_edit_content():void{
        $this->actingAs($this->admin())->post('/admin/pages',['title'=>'CMS created page','slug'=>'cms-created-page','type'=>'page','status'=>'published','new_section_name'=>'Introduction','new_section_html'=>'<p>Created through the admin editor.</p>'])->assertRedirect();
        $this->get('/cms-created-page')->assertOk()->assertSee('Created through the admin editor.');
        $page=Page::where('slug','cms-created-page')->firstOrFail();$section=$page->sections()->first();
        $this->post('/admin/pages/'.$page->id,['title'=>$page->title,'slug'=>$page->slug,'type'=>'page','status'=>'published','sections'=>[$section->id=>['name'=>'Introduction','position'=>0,'content_html'=>'<p>Edited through the database.</p>']]])->assertRedirect();
        $this->get('/cms-created-page')->assertSee('Edited through the database.');
        $this->get('/admin/inbox')->assertOk();$this->get('/admin/globals')->assertOk();$this->get('/admin/forms')->assertOk();
    }
    public function test_media_upload_is_restricted_and_records_metadata():void{
        $this->actingAs($this->admin());
        $this->post('/admin/media',['image'=>\Illuminate\Http\UploadedFile::fake()->create('bad.php',1,'application/x-php')])->assertSessionHasErrors('image');
        $image=\Illuminate\Http\UploadedFile::fake()->createWithContent('cms-test.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $this->post('/admin/media',['image'=>$image,'alt'=>'Synthetic upload test'])->assertRedirect();
        $record=\App\Models\MediaAsset::where('alt','Synthetic upload test')->firstOrFail();
        $this->assertFileExists(public_path($record->path));unlink(public_path($record->path));
        $this->get('/admin/media')->assertOk();
    }
}
