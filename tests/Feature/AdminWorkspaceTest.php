<?php
namespace Tests\Feature;
use App\Models\{User,Page,MediaAsset};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class AdminWorkspaceTest extends TestCase
{
 use RefreshDatabase;
 protected $seed=true;
 protected $seeder=\Database\Seeders\ContentSeeder::class;
 private function admin(){ $user=User::factory()->create();$user->forceFill(['is_admin'=>true])->save();return $user; }
 public function test_new_admin_surfaces_require_an_administrator():void {
  foreach(['/admin','/admin/content','/admin/media/picker'] as $url)$this->get($url)->assertRedirect('/admin/login');
  $this->actingAs(User::factory()->create());
  foreach(['/admin','/admin/content','/admin/media/picker'] as $url)$this->get($url)->assertForbidden();
 }
 public function test_dashboard_and_content_filters_use_real_database_records():void {
  $before=Page::count();$publishedBefore=Page::published()->count();
  Page::create(['title'=>'Published article','slug'=>'published-article','type'=>'post','status'=>'published']);
  Page::create(['title'=>'Draft insurance','slug'=>'draft-insurance','type'=>'insurance','status'=>'draft']);
  $this->actingAs($this->admin());
  $this->get('/admin')->assertOk()->assertViewIs('admin.dashboard')->assertViewHas('counts',fn($counts)=>$counts['Content items']===$before+2 && $counts['Published pages']===$publishedBefore+1)->assertSee('Draft insurance');
  $this->get('/admin/content?type=post&status=published&q=Published+article')->assertOk()->assertSee('Published article')->assertDontSee('Draft insurance');
  $this->get('/admin/content?q=no-match')->assertOk()->assertSee('No matching content');
  $this->get('/admin/pages/create?type=post')->assertOk()->assertViewHas('page',fn($page)=>$page->type==='post')->assertSee('/vendor/tinymce/tinymce.min.js',false)->assertSee('data-rich-editor',false);
 }
 public function test_editor_media_picker_searches_and_uploads_locally():void {
  MediaAsset::create(['name'=>'Example lighthouse','path'=>'/assets/lighthouse.webp','alt'=>'Lighthouse','mime'=>'image/webp','size'=>100]);
  $this->actingAs($this->admin());
  $this->getJson('/admin/media/picker?q=lighthouse')->assertOk()->assertJsonCount(1,'data')->assertJsonFragment(['path'=>'/assets/lighthouse.webp']);
  $this->getJson('/admin/media/picker?q=missing')->assertOk()->assertJsonCount(0,'data');
  $this->postJson('/admin/media',['image'=>UploadedFile::fake()->create('bad.php',1,'application/x-php')])->assertUnprocessable();
  $file=UploadedFile::fake()->createWithContent('editor-test.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
  $response=$this->postJson('/admin/media',['image'=>$file,'alt'=>'Editor upload test'])->assertCreated()->assertJsonStructure(['location','alt']);
  $path=$response->json('location');$this->assertDatabaseHas('media_assets',['path'=>$path,'alt'=>'Editor upload test']);$this->assertFileExists(public_path($path));unlink(public_path($path));
 }
 public function test_login_and_logout_keep_authentication_working():void {
  $user=$this->admin();$user->password='known-test-password';$user->save();
  $this->get('/admin/login')->assertOk()->assertSee('Welcome back.')->assertSee('data-password-toggle',false);
  $this->post('/admin/login',['email'=>$user->email,'password'=>'wrong-password'])->assertSessionHasErrors('email');
  $this->post('/admin/login',['email'=>$user->email,'password'=>'known-test-password'])->assertRedirect('/admin');
  $this->assertAuthenticatedAs($user);$this->get('/admin/login')->assertRedirect('/admin');
  $this->post('/admin/logout')->assertRedirect('/admin/login');$this->assertGuest();
 }
}
