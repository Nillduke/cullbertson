<?php
namespace App\Services;

use App\Models\Page;
use Illuminate\Http\Request;

class SubmissionSource {
 public function capture(Request $request,?string $fallback=null):array {
  $url=$request->header('referer','');
  if(!in_array(parse_url($url,PHP_URL_HOST),array_filter([$request->getHost(),parse_url(config('app.url'),PHP_URL_HOST)]),true))$url='';
  if(!$url&&$fallback)$url=url($fallback);
  return $url?['sourcePage'=>$this->page($url)]:[];
 }
 private function page(string $url):array {
  $path='/'.ltrim(parse_url($url,PHP_URL_PATH)?:'/','/');
  $canonical=SeoMetadata::QUOTE_ALIASES[SeoMetadata::path($path)]??SeoMetadata::path($path);
  $title=Page::where('slug',trim($canonical,'/'))->value('title');
  // Only the quote service query is useful here; never retain tracking/session parameters.
  parse_str(parse_url($url,PHP_URL_QUERY)??'',$query);
  $suffix=isset($query['service'])&&is_string($query['service'])?'?service='.rawurlencode(mb_substr($query['service'],0,80)):'';
  return ['url'=>rtrim(config('app.url'),'/').$path.$suffix,'title'=>$title?:''];
 }
 public function details(array $item):array {
  $payload=$item['payload'];$services=\App\Models\FormDefinition::data('services')['services']??[];
  $path=$item['kind']==='quotes'?($item['service']==='auto'?'/get-a-quote/get-a-auto-insurance-quote/':(!empty($services[$item['service']]['path'])?'/get-a-quote/'.trim($services[$item['service']]['path'],'/').'/':'/quote/?service='.rawurlencode($item['service']))):null;
  $form=$path?$this->page($path):null;
  $source=$payload['sourcePage']??null;
  $legacy=$payload['wordpress']['entry']??[];
  if(!$source){$url=$legacy['source_url']??$legacy['referer']??$payload['page']??null;if(is_string($url)&&$url!=='')$source=$this->page($url);}
  return ['form_title'=>$payload['wordpress']['form_name']??(($form['title']??'')?:$item['subject']),'form_url'=>$form['url']??null,'source_page'=>$source];
 }
}
