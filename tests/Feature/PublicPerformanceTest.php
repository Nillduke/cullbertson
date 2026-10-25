<?php

namespace Tests\Feature;

use App\Models\FormDefinition;
use App\Models\QuoteRequest;
use App\Services\ResponsiveImages;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = ContentSeeder::class;

    public function test_quote_entry_is_ready_before_javascript_and_remains_database_driven(): void
    {
        $this->get('/get-a-quote/')->assertOk()->assertSee('class="service-picker"', false)->assertSee('service-picker-layout', false)->assertSee('id="quote-bootstrap"', false)->assertDontSee('Choose your insurance to get started.');
        $form = FormDefinition::where('key', 'services')->firstOrFail();
        $definition = $form->definition;
        $definition['picker']['title'] = 'A different database heading';
        $form->update(['definition' => $definition]);
        $this->get('/get-a-quote/')->assertOk()->assertSee('A different database heading');
        foreach (['/auto-quote/', '/get-a-quote/get-a-auto-insurance-quote/', '/get-a-quote/get-a-home-insurance-quote/'] as $url) {
            $this->get($url)->assertOk()->assertSee('name="email"', false)->assertSee('Where can we email you?')->assertSee('quote-progress-notice')->assertDontSee('Loading your quote form');
        }
        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_public_html_compresses_without_losing_seo_or_content(): void
    {
        $plain = $this->get('/insurance/auto-insurance/')->assertOk()->getContent();
        $compressed = $this->withHeader('Accept-Encoding', 'gzip')->get('/insurance/auto-insurance/')->assertOk()->assertHeader('Content-Encoding', 'gzip');
        $html = gzdecode($compressed->getContent());
        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('srcset="/assets/optimized/', $html);
        $this->assertLessThan(strlen($plain) / 2, strlen($compressed->getContent()));
    }

    public function test_images_keep_layout_dimensions_and_external_images_are_untouched(): void
    {
        $service = app(ResponsiveImages::class);
        $html = $service->html('<img src="/assets/family-auto.webp" alt="Family" width="1920" height="1076" fetchpriority="high">');
        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('width="1920" height="1076"', $html);
        $this->assertStringContainsString('srcset=', $html);
        $external = '<img src="https://other.example/assets/family-auto.webp" alt="External">';
        $this->assertSame($external, $service->html($external));
        $this->assertFalse($service->build('/../.env'));
    }

    public function test_homepage_video_waits_for_playback_and_has_a_prioritized_poster(): void
    {
        $response = $this->get('/')->assertOk();
        $html = $response->getContent();
        preg_match('~<video\b.*?</video>~s', $html, $match);
        $this->assertStringContainsString('preload="none"', $match[0]);
        $this->assertStringContainsString('data-src="/assets/florida-life-optimized.mp4"', $match[0]);
        $this->assertStringNotContainsString('autoplay', $match[0]);
        $this->assertStringContainsString('rel="preload" as="image"', $html);
    }
}
