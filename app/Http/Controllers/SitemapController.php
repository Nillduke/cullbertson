<?php
namespace App\Http\Controllers;
use App\Models\{Page,Category,Tag,Author};
class SitemapController extends Controller {
 public function index(){
  $keys=['posts-post-1','posts-page-1','posts-agency-staff-1','taxonomies-category-1','taxonomies-post_tag-1','users-1'];
  $xml='<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
  foreach($keys as $key)$xml.='<sitemap><loc>'.$this->escape(url($key==='posts-page-1'?'/sitemap-pages.xml':'/wp-sitemap-'.$key.'.xml')).'</loc></sitemap>';
  return response($xml.'</sitemapindex>',200,['Content-Type'=>'application/xml; charset=UTF-8']);
 }
 public function entries(string $key){
  if($key==='posts-page-1')return $this->pages();
  $publicPosts=fn($q)=>$q->published()->where('type','post');
  $paths=match($key){
   'posts-post-1'=>Page::published()->where('type','post')->pluck('slug'),
   'posts-page-1'=>Page::published()->whereIn('type',['page','insurance','blog_index'])->pluck('slug'),
   'posts-agency-staff-1'=>Page::published()->where('type','staff')->pluck('slug'),
   'taxonomies-category-1'=>Category::whereHas('pages',$publicPosts)->get()->map(fn($x)=>$x->path()),
   'taxonomies-post_tag-1'=>Tag::whereHas('pages',$publicPosts)->get()->map(fn($x)=>$x->path()),
   'users-1'=>Author::whereHas('pages',$publicPosts)->get()->map(fn($x)=>$x->path()),
   default=>abort(404),
  };
  $noindex=\App\Models\SeoProfile::where('fields->robots','like','%noindex%')->pluck('url_path')->all();
  $paths=$paths->reject(fn($path)=>isset(\App\Services\SeoMetadata::QUOTE_ALIASES[\App\Services\SeoMetadata::path($path)])||in_array(\App\Services\SeoMetadata::path($path),$noindex,true));
  $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
  foreach($paths as $path)$xml.='<url><loc>'.$this->escape(url('/'.trim($path,'/')).($path===''?'':'/')).'</loc></url>';
  return response($xml.'</urlset>',200,['Content-Type'=>'application/xml; charset=UTF-8']);
 }
 public function pages(){
  $noindex=\App\Models\SeoProfile::where('fields->robots','like','%noindex%')->pluck('url_path')->map(fn($path)=>\App\Services\SeoMetadata::path($path))->all();
  $pages=Page::published()->whereIn('type',['page','insurance','blog_index'])->withMax('sections','updated_at')->orderBy('slug')->get();
  $xml="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
  foreach($pages as $page){
   $path=\App\Services\SeoMetadata::path($page->slug);
   if(isset(\App\Services\SeoMetadata::QUOTE_ALIASES[$path])||in_array($path,$noindex,true))continue;
   $home=$path==='/';$legal=preg_match('~(?:privacy|terms|cookies|ccpa)~',$path);
   $frequency=$legal?'yearly':(($home||$page->type==='blog_index')?'weekly':'monthly');
   $priority=$home?'1.0':($legal?'0.3':($page->type==='insurance'?'0.9':'0.7'));
   $xml.="  <url>\n    <loc>".$this->escape(url($path).($home?'':'/'))."</loc>\n";
   $modified=collect([$page->updated_at?->toDateTimeString(),$page->sections_max_updated_at])->filter()->max();
   if($modified)$xml.='    <lastmod>'.\Illuminate\Support\Carbon::parse($modified)->toAtomString()."</lastmod>\n";
   $xml.='    <changefreq>'.$frequency."</changefreq>\n    <priority>".$priority."</priority>\n  </url>\n";
  }
  return response($xml.'</urlset>'."\n",200,['Content-Type'=>'application/xml; charset=UTF-8','Cache-Control'=>'no-cache','X-Content-Type-Options'=>'nosniff']);
 }
 private function escape(string $url):string{return htmlspecialchars($url,ENT_XML1|ENT_QUOTES,'UTF-8');}
}
