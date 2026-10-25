<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_sections')->where('content_html', 'like', '%id="quote-product-icon"%')->orderBy('id')->each(function ($section) {
            $html = preg_replace('~<span class="quote-bundle-icon">.*?</span>~s', '<img alt="" height="45" src="/assets/quote-service-home-auto.svg" width="57"/>', $section->content_html);
            $html = preg_replace('~<span aria-hidden="true" class="quote-dots">.*?</span>~s', '<img alt="" height="45" src="/assets/quote-service-all.svg" width="57"/>', $html);
            $html = str_replace(['/assets/quote-auto.svg', '/assets/quote-home.svg', '/assets/quote-recreation.svg'], ['/assets/quote-service-auto.svg', '/assets/quote-service-home.svg', '/assets/quote-service-motorcycle.svg'], $html);
            DB::table('page_sections')->where('id', $section->id)->update(['content_html' => $html, 'updated_at' => now()]);
        });
        $row = DB::table('form_definitions')->where('key', 'homepage-products')->first();
        if ($row) {
            $data = json_decode($row->definition, true, flags: JSON_THROW_ON_ERROR);
            foreach (['auto'=>'auto', 'bundle'=>'home-auto', 'property'=>'home', 'recreation'=>'motorcycle', 'Condo'=>'condo', 'Renters'=>'renters', 'Flood'=>'flood', 'Boat'=>'boat', 'RV'=>'rv'] as $key=>$icon) {
                if (isset($data[$key])) $data[$key]['icon'] = 'service-'.$icon;
            }
            DB::table('form_definitions')->where('id', $row->id)->update(['definition'=>json_encode($data), 'updated_at'=>now()]);
        }
    }

    public function down(): void {}
};
