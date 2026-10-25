<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class SeoProfileSeeder extends Seeder {
 public function run():void {
  $rows=[];foreach(json_decode(file_get_contents(database_path('import/seo-profiles.json')),true) as $profile)$rows[]=['url_path'=>$profile['url_path'],'fields'=>json_encode($profile['fields'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'source'=>json_encode($profile['source'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'created_at'=>now(),'updated_at'=>now()];
  DB::transaction(function()use($rows){foreach(array_chunk($rows,100) as $chunk)DB::table('seo_profiles')->insertOrIgnore($chunk);});
 }
}
