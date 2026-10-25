<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{SeoProfile,Page,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use DOMDocument;
use DOMXPath;
class SeoParityTest extends TestCase {
 use RefreshDatabase;protected $seed=true;protected $seeder=\Database\Seeders\ContentSeeder::class;
 private function parseHead(string $html):DOMXPath{$d=new DOMDocument();$before=libxml_use_internal_errors(true);$d->loadHTML('<?xml encoding="UTF-8"?>'.$html);libxml_clear_errors();libxml_use_internal_errors($before);return new DOMXPath($d);}
 private function value(DOMXPath $x,string $key):string {return match($key){'title'=>$x->evaluate('string(//head/title)'),'canonical'=>$x->evaluate('string(//head/link[@rel="canonical"]/@href)'),'lang'=>$x->evaluate('string(/html/@lang)'),default=>$x->evaluate('string(//head/meta[@name="'.$key.'" or @property="'.$key.'"]/@content)')};}
 public function test_every_original_destination_outputs_the_approved_seo_fields():void {
  config(['app.search_engine_indexing'=>true]);$source=json_decode(file_get_contents(database_path('import/seo-profiles.json')),true);$this->assertDatabaseCount('seo_profiles',297);
  foreach($source as $profile){$response=$this->get($profile['url_path'])->assertOk();$x=$this->parseHead($response->getContent());foreach($profile['fields'] as $key=>$expected){if(in_array($key,['schemas','hreflang','x_robots_tag']))continue;$actual=$this->value($x,$key);if(in_array($key,['canonical','og:url','og:image','twitter:image'])&&$expected!=='')$actual=parse_url($actual,PHP_URL_PATH).(parse_url($actual,PHP_URL_QUERY)?'?'.parse_url($actual,PHP_URL_QUERY):'');$this->assertSame($expected,$actual,$profile['url_path'].' '.$key);}$this->assertSame(1,$x->query('//head/title')->length);$this->assertSame(1,$x->query('//head/link[@rel="canonical"]')->length);$this->assertSame(0,$x->query('//script[@type="application/ld+json"]')->length);}
 }
 public function test_staging_stays_blocked_and_production_robots_has_the_sitemap():void {
  config(['app.search_engine_indexing'=>false]);$x=$this->parseHead($this->get('/')->assertOk()->getContent());$this->assertSame('noindex, nofollow',$this->value($x,'robots'));$this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
  config(['app.search_engine_indexing'=>true]);$this->get('/robots.txt')->assertOk()->assertSee('Sitemap:')->assertSee('/wp-sitemap.xml')->assertSee('Disallow: /admin/');$x=$this->parseHead($this->get('/')->getContent());$this->assertSame('max-image-preview:large',$this->value($x,'robots'));
 }
 public function test_raw_saved_fields_are_retained_and_reseeding_does_not_overwrite_edits():void {
  $profile=SeoProfile::where('url_path','/')->firstOrFail();$this->assertSame('Culbertson Agency | Fort Myers Insurance Experts',$profile->fields['title']);$this->assertArrayHasKey('_yoast_wpseo_focuskw',$profile->source['saved_fields']);$this->assertArrayHasKey('_yoast_wpseo_linkdex',$profile->source['saved_fields']);$fields=$profile->fields;$fields['title']='Edited title retained';$profile->update(['fields'=>$fields]);(new \Database\Seeders\SeoProfileSeeder)->run();$this->assertSame('Edited title retained',$profile->fresh()->fields['title']);$this->assertDatabaseCount('seo_profiles',297);
 }
 public function test_seo_editor_requires_admin_and_updates_metadata_without_changing_content():void {
  $profile=SeoProfile::firstOrFail();$this->get('/admin/seo')->assertRedirect('/admin/login');$user=User::factory()->create();$this->actingAs($user)->get('/admin/seo')->assertForbidden();$user->forceFill(['is_admin'=>true])->save();$this->actingAs($user)->get('/admin/seo')->assertOk()->assertSee('SEO &amp; metadata',false);$this->get('/admin/seo/'.$profile->id.'/edit')->assertOk();
  $original=$profile->source;$page=Page::where('slug',trim($profile->url_path,'/'))->first();$title=$page?->title;$fields=$profile->fields;$fields['title']='A different SEO title';$fields['description']='A precise database description.';
  $this->post('/admin/seo/'.$profile->id,['url_path'=>$profile->url_path,'fields'=>$fields,'schemas_json'=>'[]','hreflang_json'=>'[]','source'=>['changed'=>true]])->assertRedirect();$this->assertSame($original,$profile->fresh()->source);if($page)$this->assertSame($title,$page->fresh()->title);$this->get($profile->url_path)->assertSee('<title>A different SEO title</title>',false);
  $fields['canonical']='javascript:alert(1)';$this->post('/admin/seo/'.$profile->id,['url_path'=>$profile->url_path,'fields'=>$fields])->assertSessionHasErrors('fields.canonical');
 }
 public function test_legacy_social_images_work_at_their_original_paths_and_private_files_are_not_exposed():void {
  foreach(json_decode(file_get_contents(database_path('import/seo-media.json')),true) as $image)$this->get($image['original_path'])->assertOk()->assertHeader('X-Content-Type-Options','nosniff');$this->get('/wp-content/uploads/../../.env')->assertNotFound();$this->get('/wp-content/uploads/2026/test.php')->assertNotFound();
 }
 public function test_new_content_and_pagination_use_separate_canonicals_and_json_ld_is_escaped():void {
  config(['app.search_engine_indexing'=>true]);$page=Page::create(['title'=>'New page title','description'=>'New page description','slug'=>'new-seo-page','type'=>'page','status'=>'published','presentation'=>[]]);$response=$this->get('/new-seo-page/')->assertOk();$x=$this->parseHead($response->getContent());$this->assertSame('New page title',$this->value($x,'title'));$this->assertSame('/new-seo-page/',parse_url($this->value($x,'canonical'),PHP_URL_PATH));
  $x=$this->parseHead($this->get('/expert-insurance-insights-and-tips-culbertson-agency-articles/page/2/')->assertOk()->getContent());$this->assertStringEndsWith('/page/2/',$this->value($x,'canonical'));$this->assertStringContainsString('Page 2',$this->value($x,'title'));
  $p=SeoProfile::where('url_path','/')->firstOrFail();$f=$p->fields;$f['schemas']=[['@type'=>'WebSite','name'=>'</script><script>alert(1)</script>']];$p->update(['fields'=>$f]);$body=$this->get('/')->getContent();$this->assertStringNotContainsString('</script><script>alert(1)',$body);$this->assertStringContainsString('\\u003C/script\\u003E',$body);
 }
}
