<?php
namespace Tests\Feature;

use App\Models\{Page,User,SeoProfile};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapSettingsTest extends TestCase
{
 use RefreshDatabase;
 private function page(string $slug):Page{return Page::create(['slug'=>$slug,'title'=>'Sitemap test page','type'=>'insurance','status'=>'published']);}
 private function admin():void{$this->actingAs(User::factory()->create(['is_admin'=>true]));}
 public function test_admin_can_publish_overrides_exclude_and_restore_automatic_entries():void{
  $this->page('insurance/test');$this->admin();
  $this->get('/admin/sitemap')->assertOk()->assertSee('/insurance/test/');
  $this->get('/admin/sitemap/edit?path=%2Finsurance%2Ftest%2F')->assertOk()->assertSee('Last modified override');
  $input=['path'=>'/insurance/test/','action'=>'save','visibility'=>'auto','frequency'=>'daily','priority'=>'0.5','modified'=>'2026-01-01T12:30'];
  $this->post('/admin/sitemap',$input)->assertSessionHasNoErrors()->assertSessionHas('success');
  $this->get('/sitemap-pages.xml')->assertSee('<changefreq>daily</changefreq>',false)->assertSee('<priority>0.5</priority>',false)->assertSee('<lastmod>2026-01-01T12:30:00.000Z</lastmod>',false);
  $this->post('/admin/sitemap',array_replace($input,['visibility'=>'exclude']))->assertSessionHasNoErrors();
  $this->get('/sitemap-pages.xml')->assertDontSee('/insurance/test/',false);
  $this->assertDatabaseHas('pages',['slug'=>'insurance/test','status'=>'published']);
  $this->post('/admin/sitemap',['path'=>$input['path'],'action'=>'reset'])->assertSessionHasNoErrors();
  $this->get('/sitemap-pages.xml')->assertSee('/insurance/test/',false)->assertSee('<priority>0.9</priority>',false)->assertSee('<changefreq>monthly</changefreq>',false);
 }
 public function test_access_validation_and_noindex_protection():void{
  $this->page('hidden');SeoProfile::create(['url_path'=>'/hidden/','fields'=>['robots'=>'noindex,follow']]);
  $this->get('/admin/sitemap')->assertRedirect('/admin/login');
  $this->post('/admin/sitemap',[])->assertRedirect('/admin/login');
  $this->actingAs(User::factory()->create(['is_admin'=>false]))->get('/admin/sitemap')->assertForbidden();
  $this->admin();$input=['path'=>'/hidden/','action'=>'save','visibility'=>'auto'];
  $this->post('/admin/sitemap',$input)->assertSessionHasNoErrors();
  $this->get('/sitemap-pages.xml')->assertDontSee('/hidden/',false);
  $this->get('/admin/sitemap/edit?path=%2Fhidden%2F')->assertSee('SEO noindex directive');
  $this->post('/admin/sitemap',$input+['priority'=>'9','frequency'=>'invalid','modified'=>now()->addDay()->toDateTimeString()])->assertSessionHasErrors(['priority','frequency','modified']);
  $this->post('/admin/sitemap',array_replace($input,['path'=>'https://other.example/']))->assertNotFound();
  $this->get('/admin/sitemap/edit?path=%2Fmissing%2F')->assertNotFound();
 }
}
