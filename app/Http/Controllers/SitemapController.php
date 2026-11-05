<?php
namespace App\Http\Controllers;
use App\Models\{Page,Category,Tag,Author};
class SitemapController extends Controller {
 private const URLSET_OPEN='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';
 public function index(){
  $keys=['posts-post-1','posts-page-1','posts-agency-staff-1','taxonomies-category-1','taxonomies-post_tag-1','users-1'];
  $xml='<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
  foreach($keys as $key)$xml.='<sitemap><loc>'.$this->escape(url($key==='posts-page-1'?'/sitemap-pages.xml':'/wp-sitemap-'.$key.'.xml')).'</loc></sitemap>';
  return response($xml.'</sitemapindex>',200,['Content-Type'=>'application/xml; charset=UTF-8']);
 }
 public function entries(string $key){
  if($key==='posts-page-1')return $this->pages();
  $publicPosts=fn($q)=>$q->published()->where('type','post');
  $records=match($key){
   'posts-post-1'=>Page::published()->where('type','post')->withMax('sections','updated_at')->get(),
   'posts-agency-staff-1'=>Page::published()->where('type','staff')->withMax('sections','updated_at')->get(),
   'taxonomies-category-1'=>Category::whereHas('pages',$publicPosts)->withMax(['pages'=>$publicPosts],'updated_at')->get(),
   'taxonomies-post_tag-1'=>Tag::whereHas('pages',$publicPosts)->withMax(['pages'=>$publicPosts],'updated_at')->get(),
   'users-1'=>Author::whereHas('pages',$publicPosts)->withMax(['pages'=>$publicPosts],'updated_at')->get(),
   default=>abort(404),
  };
  return $this->renderEntries($records);
 }
 public function pages(){
  $pages=Page::published()->whereIn('type',['page','insurance','blog_index'])->withMax('sections','updated_at')->orderBy('slug')->get();
  return $this->renderEntries($pages);
 }
 private function renderEntries($records){
  $settings=app(\App\Services\SitemapSettings::class);$overrides=$settings->overrides();$noindex=$settings->noindex();
  $xml="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".self::URLSET_OPEN."\n";
  foreach($records as $record){
   $entry=$settings->entry($record,$overrides,$noindex);if(!$entry['included'])continue;
   $path=$entry['path'];$home=$path==='/';$frequency=$entry['frequency'];$priority=$entry['priority'];
   $xml.="  <url>\n    <loc>".$this->escape(url($path).($home?'':'/'))."</loc>\n";
   $modified=$entry['modified'];
   if($modified)$xml.='    <lastmod>'.\Illuminate\Support\Carbon::parse($modified)->utc()->format('Y-m-d\TH:i:s.v\Z')."</lastmod>\n";
   $xml.='    <changefreq>'.$frequency."</changefreq>\n    <priority>".$priority."</priority>\n  </url>\n";
  }
  return response($xml.'</urlset>'."\n",200,['Content-Type'=>'application/xml; charset=UTF-8','Cache-Control'=>'no-cache','X-Content-Type-Options'=>'nosniff']);
 }
 private function escape(string $url):string{return htmlspecialchars($url,ENT_XML1|ENT_QUOTES,'UTF-8');}
}
