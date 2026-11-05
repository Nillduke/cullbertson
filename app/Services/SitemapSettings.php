<?php
namespace App\Services;

use App\Models\{FormDefinition,Page,Category,Tag,Author,SeoProfile};

class SitemapSettings
{
 public const FREQUENCIES=['always','hourly','daily','weekly','monthly','yearly','never'];
 public function overrides():array{return FormDefinition::where('key','sitemap-settings')->first()?->definition['entries']??[];}
 public function noindex():array{return SeoProfile::where('fields->robots','like','%noindex%')->pluck('url_path')->map(fn($path)=>SeoMetadata::path($path))->all();}
 public function catalog(){
  $posts=fn($q)=>$q->published()->where('type','post');
  return Page::published()->whereIn('type',['page','insurance','blog_index','post','staff'])->withMax('sections','updated_at')->get()->toBase()
   ->concat(Category::whereHas('pages',$posts)->withMax(['pages'=>$posts],'updated_at')->get())
   ->concat(Tag::whereHas('pages',$posts)->withMax(['pages'=>$posts],'updated_at')->get())
   ->concat(Author::whereHas('pages',$posts)->withMax(['pages'=>$posts],'updated_at')->get());
 }
 public function entry($record,array $overrides,array $noindex):array{
  $path=SeoMetadata::path($record instanceof Page?$record->slug:$record->path());$home=$path==='/';$legal=preg_match('~(?:privacy|terms|cookies|ccpa)~',$path);
  $type=$record instanceof Page?$record->type:match(true){$record instanceof Category=>'category',$record instanceof Tag=>'tag',default=>'author'};
  $frequency=$legal?'yearly':(($home||!($record instanceof Page)||$type==='blog_index')?'weekly':'monthly');
  $priority=$home?'1.0':($legal?'0.3':($type==='insurance'?'0.9':'0.7'));
  $modified=collect([$record->updated_at?->toDateTimeString(),$record->sections_max_updated_at,$record->pages_max_updated_at])->filter()->max();
  $override=$overrides[$path]??[];
  $reason=isset(SeoMetadata::QUOTE_ALIASES[$path])?'Duplicate quote URL':(in_array($path,$noindex,true)?'SEO noindex directive':(!empty($override['excluded'])?'Excluded in sitemap settings':''));
  return ['path'=>$path,'title'=>$record instanceof Page?$record->title:$record->name,'type'=>$type,'included'=>$reason==='','reason'=>$reason,'custom'=>!empty($override),
   'frequency'=>$override['frequency']??$frequency,'priority'=>$override['priority']??$priority,
   'modified'=>empty($override['modified'])?$modified:$override['modified'],'override'=>$override];
 }
 public function entries(){
  $overrides=$this->overrides();$noindex=$this->noindex();
  return $this->catalog()->map(fn($record)=>$this->entry($record,$overrides,$noindex))->sortBy('path')->values();
 }
}
