<?php
namespace Database\Seeders;
use App\Models\Page;
use Illuminate\Database\Seeder;
class InsuranceContentSeeder extends Seeder {
    public function run():void {
        $source=json_decode(file_get_contents(database_path('import/insurance-content.json')),true,512,JSON_THROW_ON_ERROR);
        $base=reset($source);
        $common=['home_label','insurance_label','eyebrow','explore_label','quote_eyebrow','quote_body','quote_button','zip_label','zip_placeholder','zip_validation','phone_intro','phone','phone_url','feature_eyebrow','feature_link_label','coverage_eyebrow','coverage_note','faq_eyebrow','resources_label','resources'];
        \App\Models\FormDefinition::firstOrCreate(['key'=>'insurance-defaults'],['name'=>'New insurance page defaults','definition'=>array_intersect_key($base,array_flip($common))]);
        // Installation/import only. Public requests read Page.insurance_content directly.
        foreach($source as $slug=>$content){
            $page=Page::where('slug',$slug)->where('type','insurance')->first();
            if($page && $page->insurance_content===null)$page->update(['insurance_content'=>$content]);
        }
    }
}
