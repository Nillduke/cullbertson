<?php
namespace App\Services;
use App\Models\SeoProfile;
use Illuminate\Http\Request;
class SeoMetadata {
 public static function path(string $path):string {return trim($path,'/')===''?'/':'/'.trim($path,'/').'/';}
 public function forRequest(Request $request,array $data=[]):array {
  $path=self::path($request->path());$page=max(1,$request->integer('page',1));$base=$path;
  if(preg_match('~^(.*)/page/([1-9][0-9]*)/$~',$path,$match)){$base=self::path($match[1]);$page=(int)$match[2];}
  elseif($page>1)$path=rtrim($path,'/').'/page/'.$page.'/';
  $profile=SeoProfile::where('url_path',$path)->first();$baseProfile=$profile?:($page>1?SeoProfile::where('url_path',$base)->first():null);
  $fields=$baseProfile?->fields??[];$title=$fields['title']??($data['title']??'Culbertson Agency');
  if($page>1&&!$profile){$title.=' – Page '.$page;$fields['canonical']=$path;if(isset($fields['og:title']))$fields['og:title']=$title;if(isset($fields['twitter:title']))$fields['twitter:title']=$title;if(isset($fields['og:url']))$fields['og:url']=$path;}
  $fields=array_merge(['description'=>$data['description']??'','canonical'=>$path,'robots'=>'max-image-preview:large','lang'=>'en-US','schemas'=>[],'hreflang'=>[]],$fields,['title'=>$title]);
  $absolute=fn($value)=>str_starts_with($value,'/')?rtrim(config('app.url')?:$request->getSchemeAndHttpHost(),'/').$value:$value;
  foreach(['canonical','og:url','og:image','twitter:image'] as $key)if(!empty($fields[$key]))$fields[$key]=$absolute($fields[$key]);
  foreach($fields['hreflang'] as &$alternate){if(isset($alternate['url']))$alternate['url']=$absolute($alternate['url']);}unset($alternate);
  if(!config('app.search_engine_indexing'))$fields['robots']='noindex, nofollow';
  return $fields;
 }
}
