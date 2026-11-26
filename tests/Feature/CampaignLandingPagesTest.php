<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignLandingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_start_a_new_campaign_page_from_the_landing_page_template(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/pages/create?type=page&template=landing')
            ->assertOk()
            ->assertSee('Campaign landing page')
            ->assertSee('name="presentation_template"', false);
        $this->actingAs($admin)->get('/admin/content')->assertOk()->assertSee('Create campaign landing page');

        $this->actingAs($admin)->post('/admin/pages', [
            'title' => 'Home insurance review',
            'slug' => 'home-insurance-review',
            'type' => 'page',
            'status' => 'draft',
            'presentation_template' => 'landing',
        ])->assertRedirect();

        $page = Page::where('slug', 'home-insurance-review')->with('sections')->firstOrFail();
        $this->assertSame('landing', $page->presentation['template']);
        $this->assertGreaterThanOrEqual(5, $page->sections->count());
        $this->assertTrue($page->sections->contains(fn ($section) => str_contains($section->content_html, 'auto-lp-image-section')));
        $this->assertTrue($page->sections->contains(fn ($section) => str_contains($section->content_html, 'auto-lp-compare')));
        $image = $page->sections->first(fn ($section) => str_contains($section->content_html, 'auto-lp-image-section'));
        $comparison = $page->sections->first(fn ($section) => str_contains($section->content_html, 'auto-lp-compare'));
        $this->assertLessThan($comparison->position, $image->position);
    }

    public function test_admin_can_insert_an_image_section_before_the_comparison(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $page = Page::where('slug', 'auto-insurance')->firstOrFail();
        $comparison = $page->sections()->where('name', 'Auto renewal comparison')->firstOrFail();

        $this->actingAs($admin)->post('/admin/pages/'.$page->id, [
            'title' => $page->title,
            'slug' => $page->slug,
            'type' => 'page',
            'status' => 'published',
            'presentation_template' => 'landing',
            'new_section_name' => 'Additional campaign image',
            'new_section_position' => $comparison->position,
            'new_section_html' => '<section class="auto-lp-image-section"><img src="/assets/family-auto.webp" alt="Example"></section>',
        ])->assertRedirect();

        $ordered = $page->sections()->orderBy('position')->get();
        $image = $ordered->firstWhere('name', 'Additional campaign image');
        $comparison = $ordered->firstWhere('name', 'Auto renewal comparison');
        $this->assertNotNull($image);
        $this->assertLessThan($comparison->position, $image->position);
    }

}
