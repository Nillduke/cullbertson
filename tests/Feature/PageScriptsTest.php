<?php
namespace Tests\Feature;

use App\Models\{FormDefinition,Page,User,QuoteRequest};
use App\Services\PageScripts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PageScriptsTest extends TestCase
{
 use RefreshDatabase;
 protected $seed=true;protected $seeder=\Database\Seeders\ContentSeeder::class;
 private function admin():void{$this->actingAs(User::factory()->create(['is_admin'=>true]));}
 private function save(string $page,array $extra=[]){return $this->post('/admin/page-scripts',array_replace(['page'=>$page,'enabled'=>1,'header'=>'<script id="page-only-head">window.pageHead=true;</script>','footer'=>'<script id="page-only-foot">window.pageFoot=true;</script>'],$extra));}

 public function test_admin_catalog_access_and_validation():void{
  $this->get('/admin/page-scripts')->assertRedirect('/admin/login');
  $this->actingAs(User::factory()->create(['is_admin'=>false]));$this->save('/')->assertForbidden();
  $this->admin();$this->get('/admin/page-scripts')->assertOk()->assertSee('Choose a page')->assertSee('/get-a-quote/')->assertSee('/get-a-quote/get-a-auto-insurance-quote/')->assertSee('/quote/?service=home-auto',false)->assertSee('/quote/thank-you/');
  $this->save('/admin/')->assertSessionHasErrors('page');
  $this->save('/', ['header'=>str_repeat('x',200001)])->assertSessionHasErrors('header');
  $this->get('/admin/page-scripts?page=%2Fmissing%2F')->assertNotFound();
 }

 public function test_scripts_are_scoped_escaped_correctly_placed_and_can_be_disabled_or_cleared():void{
  Page::create(['slug'=>'script-check','title'=>'Script check','type'=>'page','status'=>'published','presentation'=>[]]);
  FormDefinition::updateOrCreate(['key'=>'global-scripts'],['name'=>'Global scripts','definition'=>['header'=>'<script id="global-head"></script>','footer'=>'<script id="global-foot"></script>','thank_you'=>'<script id="goal-only"></script>']]);
  $this->admin();$this->save('/script-check/')->assertSessionHasNoErrors();
  $this->get('/admin/page-scripts?page=%2Fscript-check%2F')->assertOk()->assertSee('page-only-head')->assertDontSee('<script id="page-only-head">',false);
  $html=$this->get('/script-check')->assertOk()->getContent();
  $this->assertLessThan(strpos($html,'</head>'),strpos($html,'id="page-only-head"'));
  $this->assertGreaterThan(strpos($html,'id="global-head"'),strpos($html,'id="page-only-head"'));
  $this->assertGreaterThan(strpos($html,'id="global-foot"'),strpos($html,'id="page-only-foot"'));
  $this->assertLessThan(strpos($html,'</body>'),strpos($html,'id="page-only-foot"'));
  $this->assertStringNotContainsString('id="goal-only"',$html);
  $this->get('/')->assertOk()->assertDontSee('id="page-only-head"',false)->assertSee('id="global-head"',false);
  $this->save('/script-check/',['enabled'=>0])->assertSessionHasNoErrors();
  $this->get('/script-check')->assertDontSee('id="page-only-head"',false);
  $this->assertStringContainsString('page-only-head',app(PageScripts::class)->settings()['/script-check/']['header']);
  $this->save('/script-check/',['header'=>'','footer'=>''])->assertSessionHasNoErrors();
  $this->get('/script-check')->assertDontSee('id="page-only-head"',false);
 }

 public function test_quote_aliases_service_specific_forms_pagination_and_confirmation_guard():void{
  $this->admin();$service=app(PageScripts::class);
  $this->save('/get-a-quote/get-a-auto-insurance-quote/')->assertSessionHasNoErrors();
  foreach(['/auto-quote/','/get-a-quote/get-a-auto-insurance-quote/'] as $url)$this->get($url)->assertOk()->assertSee('id="page-only-head"',false);
  $this->get('/get-a-quote/')->assertOk()->assertDontSee('id="page-only-head"',false);
  $this->save('/get-a-quote/get-a-home-insurance-quote/')->assertSessionHasNoErrors();
  $this->get('/quote/?service=home&utm_source=test')->assertOk()->assertSee('id="page-only-foot"',false);
  $this->get('/quote/?service=condo')->assertOk()->assertDontSee('id="page-only-foot"',false);
  $this->assertSame('/quote/?service=home-auto',$service->target(Request::create('/quote/?service=home-auto')));
  $this->assertSame('/category/news/',$service->target(Request::create('/category/news/page/2/')));
  $this->save('/quote/thank-you/')->assertSessionHasNoErrors();
  $request=Request::create('/quote/thank-you/?receipt=example');
  $this->assertSame('',$service->forRequest($request)['footer']);
  $this->assertStringContainsString('page-only-foot',$service->forRequest($request,true)['footer']);
  $this->get('/quote/thank-you/')->assertRedirect('/get-a-quote');
  $this->assertSame('',$service->forRequest(Request::create('/admin/page-scripts'))['header']);
 }

 public function test_thank_you_snippets_execute_only_on_first_valid_confirmation_view():void{
  $this->withCredentials();$this->withSession([]);$this->withCookie(config('session.cookie'),session()->getId());$this->admin();
  $this->save('/quote/thank-you/',['css_enabled'=>1,'css'=>'.quote-thanks-card { --test-accent: blue; }'])->assertSessionHasNoErrors();
  $quote=QuoteRequest::withoutEvents(fn()=>QuoteRequest::create(['reference'=>'SCRIPT-TEST-ONLY','service'=>'home','completion_status'=>'completed','payload'=>[]]));
  $token=str_repeat('a',48);$this->withSession(['quote_receipts'=>[$token=>['quote_id'=>$quote->id,'created'=>now()->timestamp,'tracking_shown'=>false]]]);
  $this->get('/quote/thank-you/?receipt='.$token)->assertOk()->assertSee('id="page-only-head"',false)->assertSee('id="page-only-foot"',false);
  $this->get('/quote/thank-you/?receipt='.$token)->assertOk()->assertDontSee('id="page-only-head"',false)->assertDontSee('id="page-only-foot"',false)->assertSee('id="page-custom-css"',false);
 }
}
