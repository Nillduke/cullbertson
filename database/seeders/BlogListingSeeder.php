<?php
namespace Database\Seeders;
use App\Models\FormDefinition;
use Illuminate\Database\Seeder;
class BlogListingSeeder extends Seeder {
    public function run():void { FormDefinition::firstOrCreate(['key'=>'blog-listing-copy'],['name'=>'Blog listing labels','definition'=>['featuredTitle'=>'Featured Articles','latestTitle'=>'More insurance insights','readMore'=>'Read more','articleTag'=>'Article']]); }
}
