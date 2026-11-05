<?php
namespace Tests\Feature;

use App\Models\{Page,SeoProfile};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesSitemapTest extends TestCase
{
 use RefreshDatabase;

 public function test_pages_sitemap_is_dynamic_with_real_modified_dates_and_legacy_alias():void
 {
  $home=Page::create(['slug'=>'','title'=>'Home','type'=>'page','status'=>'published']);
  $home->timestamps=false;$home->update(['updated_at'=>'2026-10-01 12:30:00']);
  Page::create(['slug'=>'insurance/home-insurance','title'=>'Home insurance','type'=>'insurance','status'=>'published']);
  Page::create(['slug'=>'draft','title'=>'Draft','type'=>'page','status'=>'draft']);
  Page::create(['slug'=>'future','title'=>'Future','type'=>'page','status'=>'published','published_at'=>now()->addDay()]);
  Page::create(['slug'=>'article','title'=>'Article','type'=>'post','status'=>'published']);
  Page::create(['slug'=>'auto-quote','title'=>'Alias','type'=>'page','status'=>'published']);
  Page::create(['slug'=>'hidden','title'=>'Hidden','type'=>'page','status'=>'published']);
  SeoProfile::create(['url_path'=>'/hidden/','fields'=>['robots'=>'noindex,follow']]);
  $r=$this->get('/sitemap-pages.xml')->assertOk()->assertHeader('Content-Type','application/xml; charset=UTF-8');
  $xml=simplexml_load_string($r->getContent());$this->assertNotFalse($xml);$this->assertCount(2,$xml->url);
  $this->assertSame('2026-10-01T12:30:00.000Z',(string)$xml->url[0]->lastmod);
  $this->assertSame([
   ''=>'http://www.sitemaps.org/schemas/sitemap/0.9',
   'news'=>'http://www.google.com/schemas/sitemap-news/0.9',
   'xhtml'=>'http://www.w3.org/1999/xhtml',
   'image'=>'http://www.google.com/schemas/sitemap-image/1.1',
   'video'=>'http://www.google.com/schemas/sitemap-video/1.1',
  ],$xml->getDocNamespaces());
  $this->assertSame('1.0',(string)$xml->url[0]->priority);
  $this->assertSame('monthly',(string)$xml->url[1]->changefreq);
  $this->assertSame('0.9',(string)$xml->url[1]->priority);
  $this->get('/wp-sitemap-posts-page-1.xml')->assertContent($r->getContent());
  $this->get('/wp-sitemap.xml')->assertOk()->assertSee('/sitemap-pages.xml',false);
  Page::create(['slug'=>'new-page','title'=>'New','type'=>'page','status'=>'published']);
  $this->get('/sitemap-pages.xml')->assertSee('/new-page/',false);
  $postXml=simplexml_load_string($this->get('/wp-sitemap-posts-post-1.xml')->assertOk()->getContent());
  $this->assertCount(1,$postXml->url);
  $this->assertNotEmpty((string)$postXml->url[0]->lastmod);
  $this->assertSame('monthly',(string)$postXml->url[0]->changefreq);
  $this->assertSame('0.7',(string)$postXml->url[0]->priority);
  $this->assertSame($xml->getDocNamespaces(),$postXml->getDocNamespaces());
 }
}
