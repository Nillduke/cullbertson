<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\{FormDefinition,PageSection};
use Database\Seeders\GoogleTrackingSeeder;

return new class extends Migration
{
    public function up(): void
    {
        if($row=FormDefinition::where('key','google-tracking')->first()){
            $data=$row->definition;
            unset($data['copy'],$data['consentVersion'],$data['consentDays']);
            $data['mode']='immediate';
            $row->update(['definition'=>$data,'name'=>'Google tracking destinations']);
        }
        PageSection::where('component','google_tracking_notice')->update(['content_html'=>GoogleTrackingSeeder::noticeHtml(),'updated_at'=>now()]);
    }
    public function down(): void {}
};
