<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\{FormDefinition,SeoProfile};

return new class extends Migration {
    public function up():void {
        $copy=json_decode(file_get_contents(database_path('import/quote-thank-you-copy.json')),true,flags:JSON_THROW_ON_ERROR);
        FormDefinition::firstOrCreate(['key'=>'quote-thank-you-copy'],['name'=>'Quote thank-you page content','definition'=>$copy]);
        SeoProfile::firstOrCreate(['url_path'=>'/quote/thank-you/'],['fields'=>['title'=>$copy['title'],'description'=>$copy['description'],'canonical'=>'/quote/thank-you/','robots'=>'noindex, nofollow'],'source'=>['kind'=>'quote-confirmation']]);
    }
    public function down():void {}
};
