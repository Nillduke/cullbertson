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
        $this->assertStringContainsString('data-src="/assets/florida-life.mp4"', $match[0]);
        $this->assertStringNotContainsString('florida-life-optimized.mp4', $match[0]);
        $this->assertStringNotContainsString('autoplay', $match[0]);
        $this->assertStringContainsString('rel="preload" as="image"', $html);
    }

    public function test_responsive_preload_matches_avif_picture_and_follows_viewport_metadata(): void
    {
        $html = $this->get('/insurance/')->assertOk()->getContent();
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $source = $xpath->query('//picture/source[@type="image/avif"]')->item(0);
        $preload = $xpath->query('//head/link[@rel="preload"][@type="image/avif"]')->item(0);
        $this->assertNotNull($source);
        $this->assertNotNull($preload);
        $this->assertSame($source->getAttribute('srcset'), $preload->getAttribute('imagesrcset'));
        $this->assertLessThan(strpos($html, 'type="image/avif"'), strpos($html, 'name="viewport"'));
        $this->assertStringContainsString('src="/assets/quote-family.jpg"', $html);
    }

    public function test_resizing_respects_camera_orientation(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('EXIF support is optional.');
        }
        $original = public_path();
        $temporary = storage_path('framework/testing/orientation-'.bin2hex(random_bytes(6)));
        mkdir($temporary.'/assets', 0755, true);
        $image = imagecreatetruecolor(80, 40);
        imagefilledrectangle($image, 0, 0, 39, 39, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 40, 0, 79, 39, imagecolorallocate($image, 0, 0, 255));
        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = ob_get_clean();
        imagedestroy($image);
        $exif = "Exif\0\0II\x2a\0\x08\0\0\0".pack('v', 1).pack('vvVv', 0x112, 3, 1, 6)."\0\0".pack('V', 0);
        file_put_contents($temporary.'/assets/camera.jpg', substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));
        try {
            $this->app->usePublicPath($temporary);
            $images = new ResponsiveImages;
            $this->assertTrue($images->build('/assets/camera.jpg'));
            $entry = $images->manifest()['/assets/camera.jpg'];
            $this->assertSame(40, $entry['width']);
            $this->assertSame(80, $entry['height']);
            $result = imagecreatefromwebp($temporary.$entry['variants'][40]);
            $color = imagecolorsforindex($result, imagecolorat($result, 20, 10));
            $this->assertGreaterThan($color['blue'], $color['red']);
            imagedestroy($result);
        } finally {
            $this->app->usePublicPath($original);
            $base = realpath(storage_path('framework/testing')).DIRECTORY_SEPARATOR;
            $resolved = realpath($temporary);
            if ($resolved && str_starts_with($resolved, $base)) {
                foreach (glob($temporary.'/assets/optimized/*') as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                unlink($temporary.'/assets/camera.jpg');
                if (is_dir($temporary.'/assets/optimized')) {
                    rmdir($temporary.'/assets/optimized');
                }
                rmdir($temporary.'/assets');
                rmdir($temporary);
            }
        }
    }
}
