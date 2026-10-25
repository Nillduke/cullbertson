<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class LegacyMediaController extends Controller {
 public function show(string $path){
  abort_unless(in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['jpg','jpeg','png','webp','gif','svg','ico','avif']),404);
  $root=realpath(public_path('assets/original'));$file=realpath(public_path('assets/original/'.$path));abort_unless($file&&$root&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file),404);
  return response()->file($file,['Cache-Control'=>'public, max-age=86400','X-Content-Type-Options'=>'nosniff']);
 }
 public function robots(Request $request){$body=config('app.search_engine_indexing')?"User-agent: *\nDisallow: /admin/\nDisallow: /wp-admin/\n\nSitemap: ".rtrim(config('app.url')?:$request->getSchemeAndHttpHost(),'/')."/wp-sitemap.xml\n":"User-agent: *\nDisallow: /\n";return response($body,200,['Content-Type'=>'text/plain; charset=UTF-8','Cache-Control'=>'no-store']);}
}
