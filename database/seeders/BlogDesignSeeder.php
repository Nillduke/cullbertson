<?php
namespace Database\Seeders;
use App\Models\FormDefinition;
use Illuminate\Database\Seeder;
class BlogDesignSeeder extends Seeder {
    public function run():void {
        FormDefinition::firstOrCreate(['key'=>'blog-copy'],['name'=>'Shared blog article labels','definition'=>[
            'eyebrow'=>'INSURANCE INSIGHTS','back'=>'All insurance articles','readingSingular'=>'min read','readingPlural'=>'mins read','relatedTitle'=>'Latest insurance articles',
        ]]);
    }
}
