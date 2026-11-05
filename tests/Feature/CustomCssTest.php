<?php
namespace Tests\Feature;

use App\Models\{FormDefinition,Page,User};
use App\Services\PageScripts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CustomCssTest extends TestCase
{
 use RefreshDatabase;
 protected $seed=true;protected $seeder=\Database\Seeders\ContentSeeder::class;
 private function admin():void{$this->actingAs(User::factory()->create(['is_admin'=>true]));}
 public function test_global_css_is_admin_only_preserves_scripts_and_can_be_disabled_or_cleared():void{
  $this->post('/admin/globals/css',['css'=>'body{color:red}'])->assertRedirect('/admin/login');
  $this->actingAs(User::factory()->create(['is_admin'=>false]))->post('/admin/globals/css',['css'=>'body{}'])->assertForbidden();$this->admin();
  FormDefinition::updateOrCreate(['key'=>'global-scripts'],['name'=>'Scripts','definition'=>['header'=>'<script id="keep-header"></script>','footer'=>'<script id="keep-footer"></script>','thank_you'=>'keep-goal']]);
  $css='body { --custom-global-accent: #087faf; }';
  $this->post('/admin/globals/css',['css'=>$css,'css_enabled'=>1])->assertSessionHasNoErrors();
  $this->get('/')->assertOk()->assertSee('<style id="global-custom-css">'.$css.'</style>',false)->assertSee('id="keep-header"',false)->assertSee('id="keep-footer"',false);
  $this->get('/auto-quote/')->assertOk()->assertSee($css,false);
  $this->get('/admin/globals')->assertOk()->assertSee('Global custom CSS')->assertDontSee('<style id="global-custom-css">',false);
  $this->assertSame('keep-goal',FormDefinition::where('key','global-scripts')->first()->definition['thank_you']);
  $this->post('/admin/globals/css',['css'=>'</style><script>bad()</script>','css_enabled'=>1])->assertSessionHasErrors('css');
  $this->post('/admin/globals/css',['css'=>$css,'css_enabled'=>0])->assertSessionHasNoErrors();
  $this->get('/')->assertDontSee('<style id="global-custom-css">',false);
  $this->assertSame($css,FormDefinition::where('key','global-scripts')->first()->definition['css']);
  $this->post('/admin/globals/css',['css'=>'','css_enabled'=>1])->assertSessionHasNoErrors();
  $this->get('/')->assertDontSee('<style id="global-custom-css">',false);
 }
 public function test_page_css_is_scoped_and_independent_from_scripts_and_global_css():void{
  $this->admin();$page='/get-a-quote/get-a-auto-insurance-quote/';$css='body { --page-accent: #102c3e; }';
  $this->post('/admin/globals/css',['css'=>'body { --global-accent: blue; }','css_enabled'=>1])->assertSessionHasNoErrors();
  $input=['page'=>$page,'enabled'=>0,'header'=>'<script id="disabled-page-script"></script>','footer'=>'','css'=>$css,'css_enabled'=>1];
  $this->post('/admin/page-scripts',$input)->assertSessionHasNoErrors();
  $html=$this->get('/auto-quote/')->assertOk()->assertSee($css,false)->assertDontSee('id="disabled-page-script"',false)->getContent();
  $this->assertGreaterThan(strpos($html,'id="global-custom-css"'),strpos($html,'id="page-custom-css"'));
  $this->assertLessThan(strpos($html,'</head>'),strpos($html,'id="page-custom-css"'));
  $this->get($page)->assertOk()->assertSee($css,false);
  $this->get('/get-a-quote/')->assertOk()->assertDontSee($css,false)->assertSee('id="global-custom-css"',false);
  $this->get('/admin/page-scripts?page='.urlencode($page))->assertOk()->assertSee('Enable CSS for this page')->assertDontSee('<style id="page-custom-css">',false);
  $this->post('/admin/page-scripts',array_replace($input,['css'=>'<STYLE>body{}</STYLE>']))->assertSessionHasErrors('css');
  $this->post('/admin/page-scripts',['page'=>$page,'enabled'=>0,'header'=>'','footer'=>''])->assertSessionHasNoErrors();
  $this->get('/auto-quote/')->assertSee($css,false);
  $this->post('/admin/page-scripts',array_replace($input,['css_enabled'=>0]))->assertSessionHasNoErrors();
  $this->get('/auto-quote/')->assertDontSee('id="page-custom-css"',false);
 }
}
