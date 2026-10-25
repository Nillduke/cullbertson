<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class QuoteRoutingTest extends TestCase {
 use RefreshDatabase;
 protected $seed=true;
 protected $seeder=\Database\Seeders\ContentSeeder::class;
 public function test_quote_urls_are_not_shadowed_by_asset_directories():void {
  foreach(['auto-quote','quote'] as $path){$this->assertDirectoryDoesNotExist(public_path($path));$this->get('/'.$path.'/')->assertOk()->assertSee('id="quote-step-content"',false);}
  foreach(['/auto-quote/','/get-a-quote/get-a-auto-insurance-quote/','/get-a-quote/2025-get-a-auto-insurance-quote/'] as $path)$this->get($path)->assertOk()->assertSee('/quote-assets/auto/form.js',false)->assertSee('/quote-assets/auto/form.css',false);
  $this->get('/get-a-quote/')->assertOk()->assertSee('/quote-assets/services/services.js',false);
 }
 public function test_migration_updates_existing_encoded_database_asset_paths():void {
  $page=\App\Models\Page::create(['title'=>'Asset migration fixture','slug'=>'asset-migration-fixture','type'=>'page','status'=>'draft','presentation'=>['styles'=>['/auto-quote/form.css?v=4','/quote/services.css?v=1'],'scripts'=>['/auto-quote/form.js?v=1','/quote/services.js?v=1']]]);
  $migration=require database_path('migrations/2026_10_25_010000_relocate_quote_assets.php');$migration->up();
  $this->assertSame('/quote-assets/auto/form.js?v=1',$page->fresh()->presentation['scripts'][0]);$this->assertSame('/quote-assets/services/services.css?v=1',$page->fresh()->presentation['styles'][1]);
 }
 public function test_legacy_asset_paths_remain_compatible():void {
  foreach(['auto-quote/form.css'=>'quote-assets/auto/form.css','auto-quote/form.js'=>'quote-assets/auto/form.js','quote/services.css'=>'quote-assets/services/services.css','quote/services.js'=>'quote-assets/services/services.js'] as $old=>$new){$this->get('/'.$old)->assertStatus(301)->assertRedirect('/'.$new);$this->assertFileExists(public_path($new));}
  $this->getJson('/forms/auto-options')->assertOk()->assertJsonStructure(['makes','states']);
 }
}
