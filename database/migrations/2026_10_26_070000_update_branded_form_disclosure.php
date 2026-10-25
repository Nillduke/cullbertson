<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\{FormDefinition,Page};

return new class extends Migration {
    public function up(): void {
        $row=FormDefinition::where('key','form-information')->first();
        if(!$row)return;
        $copy=json_decode(file_get_contents(database_path('import/form-information.json')),true,flags:JSON_THROW_ON_ERROR);
        $data=$row->definition;
        foreach(['notice','noticeLinks','noticeJoin','quoteNotice','smsLink','smsSuffix','choicesNotice','links'] as $key)$data[$key]=$copy[$key];
        $row->update(['definition'=>$data]);
        $pages=json_decode(file_get_contents(database_path('import/form-policy-pages.json')),true,flags:JSON_THROW_ON_ERROR);
        foreach($pages as $item){
            if(!in_array($item['slug'],['terms-of-use','sms-terms-of-service']))continue;
            if($page=Page::where('slug',$item['slug'])->first())$page->sections()->where('name','Policy content')->update(['content_html'=>'<article class="sp-shell sp-section sp-rich legal-copy">'.$item['body'].'</article>','updated_at'=>now()]);
        }
        if($page=Page::where('slug','privacy-policy-2')->first())$page->sections()->where('component','form_privacy_supplement')->update(['content_html'=>file_get_contents(database_path('import/form-privacy-supplement.html')),'updated_at'=>now()]);
    }
    public function down(): void {}
};
