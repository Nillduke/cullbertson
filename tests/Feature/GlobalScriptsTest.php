<?php

namespace Tests\Feature;

use App\Models\{FormDefinition,Page,SiteBlock,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalScriptsTest extends TestCase
{
    use RefreshDatabase;
    protected $seed=true;
    protected $seeder=\Database\Seeders\ContentSeeder::class;

    public function test_scripts_are_admin_only_publish_at_correct_positions_and_can_be_cleared():void
    {
        $this->get('/admin/globals')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->post('/admin/globals/scripts',['header'=>'<script>bad()</script>'])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        SiteBlock::updateOrCreate(['key'=>'header'],['name'=>'Header','content_html'=>'<header>Existing navigation</header>']);
        SiteBlock::updateOrCreate(['key'=>'footer'],['name'=>'Footer','content_html'=>'<footer>Existing footer</footer>']);
        FormDefinition::updateOrCreate(['key'=>'google-tracking'],['name'=>'Google','definition'=>json_decode(file_get_contents(database_path('import/google-tracking.json')),true)]);
        $this->get('/admin/globals')->assertOk()->assertSee('Header &amp; footer scripts',false)->assertSee('GTM-NPKN5562')->assertSee('Gravity Forms')->assertDontSee('<header>Existing navigation</header>',false)->assertDontSee('<script id="culbertson-gtm-snippet">',false);
        $header='<script id="custom-header">window.exampleHeader=true;</script>';
        $footer='<script id="custom-footer">window.exampleFooter=true;</script>';
        $this->post('/admin/globals/scripts',compact('header','footer'))->assertRedirect('/admin/globals');
        $this->get('/admin/globals')->assertOk()->assertDontSee($header,false)->assertSee('custom-header');
        $page=Page::create(['slug'=>'script-check','title'=>'Script check','type'=>'page','status'=>'published','description'=>'Test','presentation'=>[]]);
        FormDefinition::firstOrCreate(['key'=>'archive-copy'],['name'=>'Archives','definition'=>[]]);
        $page->sections()->create(['name'=>'Body','component'=>'design_html','position'=>0,'content_html'=>'<main>Page content</main>']);
        config(['tracking.enabled'=>true]);
        $html=$this->get('/script-check')->assertOk()->getContent();
        $this->assertLessThan(strpos($html,'</head>'),strpos($html,$header));
        $this->assertGreaterThan(strpos($html,'<body'),strpos($html,$footer));
        $this->assertLessThan(strpos($html,'</body>'),strpos($html,$footer));
        $this->assertSame(1,substr_count($html,'id="culbertson-gtm-snippet"'));
        $this->assertSame('<header>Existing navigation</header>',SiteBlock::where('key','header')->first()->content_html);
        $this->post('/admin/globals/scripts',['header'=>'','footer'=>''])->assertSessionHasNoErrors();
        $this->get('/script-check')->assertOk()->assertDontSee('id="custom-header"',false)->assertDontSee('id="custom-footer"',false);
    }

    public function test_google_ids_are_validated_without_changing_other_tracking_rules():void
    {
        $definition=json_decode(file_get_contents(database_path('import/google-tracking.json')),true);
        $row=FormDefinition::updateOrCreate(['key'=>'google-tracking'],['name'=>'Google','definition'=>$definition]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $data=array_intersect_key($definition,\App\Http\Controllers\GlobalScriptsController::GOOGLE_FIELDS);
        $this->post('/admin/globals/tracking',array_replace($data,['containerId'=>'<script>bad</script>']))->assertSessionHasErrors('containerId');
        $this->assertSame($definition,$row->fresh()->definition);
        $this->post('/admin/globals/tracking',array_replace($data,['containerId'=>'GTM-EXAMPLE1']))->assertSessionHasNoErrors();
        $this->assertSame(array_replace($definition,['containerId'=>'GTM-EXAMPLE1']),$row->fresh()->definition);
    }
}
