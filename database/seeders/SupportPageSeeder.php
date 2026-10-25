<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\{Page,PageSection,FormDefinition};
use Illuminate\Support\Facades\DB;
class SupportPageSeeder extends Seeder {
 public function run():void {
  if(!Page::exists())return;
  FormDefinition::firstOrCreate(['key'=>'support-page-copy'],['name'=>'Supporting pages, sitemap and enquiry form copy','definition'=>json_decode(file_get_contents(database_path('import/support-page-copy.json')),true)]);
  $map=Page::where('slug','site-map')->first();if($map&&!isset($map->presentation['sitemapDesignVersion'])){$p=$map->presentation??[];$p['sitemapDesignVersion']=1;$map->update(['title'=>'Site map | Culbertson Agency','presentation'=>$p]);}
  $designs=json_decode(file_get_contents(database_path('import/support-pages.json')),true);
  foreach($designs as $slug=>$sections){
   $page=Page::where('slug',$slug)->first();if(!$page||isset($page->presentation['supportDesignVersion']))continue;
   DB::transaction(function()use($page,$sections){
    $old=$page->presentation??[];$page->sections()->update(['component'=>'legacy_archive']);
    foreach($sections as $i=>$section)$page->sections()->create($section+['position'=>$i]);
    $page->update(['presentation'=>array_merge($old,['previousPresentation'=>$old,'supportDesignVersion'=>1,'template'=>'support','mainClass'=>'support-page','bodyClass'=>'content-page support-designed-page','styles'=>['/support-pages.css'],'scripts'=>['/support-pages.js']])]);
   });
  }
 }
}
