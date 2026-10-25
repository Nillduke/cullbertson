<?php
namespace App\Http\Middleware;
use Illuminate\Http\{Request,Response};
use Closure;
class AdminPathLinks {
 public function handle(Request $request,Closure $next){
  $response=$next($request);$base=$request->getBaseUrl();
  if(!$base||!$request->is('admin','admin/*')||!$response instanceof Response||!str_contains($response->headers->get('Content-Type',''),'text/html'))return $response;
  $original=$response->original;
  $html=preg_replace_callback('~\b(href|src|action)=(["\'])(/(?!/)[^"\']*)\2~i',function($m)use($base){$path=$m[3];return $m[1].'='.$m[2].(($path===$base||str_starts_with($path,$base.'/'))?$path:$base.$path).$m[2];},$response->getContent());
  $response->setContent($html);$response->original=$original;return $response;
 }
}
