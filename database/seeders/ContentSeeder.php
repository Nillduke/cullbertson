<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use App\Models\Page;
class ContentSeeder extends Seeder { public function run(): void { if(!Page::exists())Artisan::call('cms:import-design'); $this->call([InsuranceContentSeeder::class,BlogDesignSeeder::class,BlogListingSeeder::class,ContactPageSeeder::class,WordPressStructureSeeder::class,SupportPageSeeder::class]); } }
