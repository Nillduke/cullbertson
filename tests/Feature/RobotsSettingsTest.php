<?php
namespace Tests\Feature;

use App\Models\{User,FormDefinition};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotsSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_preserve_environment_indexing_rules(): void
    {
        config(['app.search_engine_indexing'=>false]);
        $this->get('/robots.txt')->assertOk()->assertContent("User-agent: *\nDisallow: /\n")->assertHeader('Content-Type','text/plain; charset=UTF-8');
        config(['app.search_engine_indexing'=>true,'app.url'=>'https://agency.example']);
        $this->get('/robots.txt')->assertSee('Disallow: /admin/',false)->assertSee('Sitemap: https://agency.example/wp-sitemap.xml',false);
    }

    public function test_only_admin_can_edit_and_published_content_is_plain_text(): void
    {
        $this->get('/admin/robots')->assertRedirect('/admin/login');
        $this->post('/admin/robots',['content'=>'User-agent: *'])->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->post('/admin/robots',['content'=>'User-agent: *'])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->get('/admin/robots')->assertOk()->assertSee('Save & publish robots.txt');
        $this->post('/admin/robots',['content'=>"User-agent: *\r\nDisallow: /private/\r\n"])->assertSessionHasNoErrors()->assertRedirect('/admin/robots');
        $this->get('/robots.txt')->assertContent("User-agent: *\nDisallow: /private/\n")->assertHeader('X-Content-Type-Options','nosniff');
        $this->post('/admin/robots',['content'=>''])->assertSessionHasErrors('content');
        $this->post('/admin/robots',['content'=>"User-agent: \x00*"])->assertSessionHasErrors('content');
        $this->get('/robots.txt')->assertContent("User-agent: *\nDisallow: /private/\n");
        $this->post('/admin/robots/reset')->assertSessionHas('success');
        $this->assertFalse(FormDefinition::where('key','robots-txt')->exists());
    }
}
