<?php
namespace App\Services;

use App\Models\{FormDefinition,Page,Category,Tag,Author};
use Illuminate\Http\Request;

class PageScripts
{
 public function settings():array{return FormDefinition::where('key','page-scripts')->first()?->definition['entries']??[];}
 public function catalog():array{
  $items=[];
  foreach(Page::orderBy('title')->get(['slug','title','type','status']) as $page){
   $path=SeoMetadata::path($page->slug);$path=SeoMetadata::QUOTE_ALIASES[$path]??$path;
   $items[$path]=['path'=>$path,'title'=>$page->title,'type'=>$page->type,'status'=>$page->status];
  }
  foreach([Category::class=>'Category',Tag::class=>'Tag',Author::class=>'Author'] as $class=>$type)foreach($class::orderBy('name')->get() as $record){$path=SeoMetadata::path($record->path());$items[$path]=['path'=>$path,'title'=>$record->name,'type'=>$type.' archive','status'=>'published'];}
  foreach(['/get-a-quote/'=>'Get a Quote','/get-a-quote/get-a-auto-insurance-quote/'=>'Auto quote','/agency-staff/'=>'Our team','/quote/thank-you/'=>'Quote thank-you (first confirmation visit only)'] as $path=>$title)$items[$path]??=['path'=>$path,'title'=>$title,'type'=>'System page','status'=>'published'];
  foreach($this->services() as $key=>$service){
   $path=$this->servicePath($key,$service);
   $items[$path]??=['path'=>$path,'title'=>($service['name']??$key).' quote','type'=>'Quote form','status'=>'published'];
  }
  uasort($items,fn($a,$b)=>strnatcasecmp($a['title'],$b['title']));return $items;
 }
 private function services():array{return FormDefinition::where('key','services')->first()?->definition['services']??[];}
 private function servicePath(string $key,array $service):string{return !empty($service['path'])?SeoMetadata::path('/get-a-quote/'.$service['path']):'/quote/?service='.rawurlencode($key);}
 public function target(Request $request):string{
  $path=SeoMetadata::path($request->path());
  if(preg_match('~^(.+)/page/[1-9][0-9]*/$~D',$path,$match))$path=SeoMetadata::path($match[1]);
  if($path==='/quote/'&&is_string($key=$request->query('service'))&&isset(($services=$this->services())[$key]))return $this->servicePath($key,$services[$key]);
  return SeoMetadata::QUOTE_ALIASES[$path]??$path;
 }
 public function forRequest(Request $request,bool $firstConfirmation=false):array{
  $empty=['header'=>'','footer'=>'','css'=>''];if($request->is('admin','admin/*'))return $empty;
  $target=$this->target($request);$entry=$this->settings()[$target]??[];
  $result=$empty;$result['css']=!empty($entry['css_enabled'])?($entry['css']??''):'';
  if(!empty($entry['enabled'])&&($target!=='/quote/thank-you/'||$firstConfirmation)){$result['header']=$entry['header']??'';$result['footer']=$entry['footer']??'';}
  return $result;
 }
}
