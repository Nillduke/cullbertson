<?php

namespace Tests\Feature;

use App\Models\FormDefinition;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleTrackingTest extends TestCase
{
    use RefreshDatabase;
    protected $seed=true;
    protected $seeder=ContentSeeder::class;

    public function test_tracking_is_explicitly_enabled_and_is_not_installed_on_admin_pages(): void
    {
        config(['tracking.enabled'=>false]);
        $this->get('/')->assertOk()->assertDontSee('culbertson-tracking-config');
        config(['tracking.enabled'=>true]);
        foreach (['/','/auto-quote/','/contact-us/','/insurance/auto-insurance/'] as $path) {
            $response=$this->get($path)->assertOk()->assertSee('culbertson-tracking-config')->assertSee('tracking.js')->assertDontSee('data-tracking-choice',false);
            $html=$response->getContent();
            foreach(['GTM-NPKN5562','G-F93LLMC4SC','AW-18511592688','G-0H056Z22M3','G-9CTSTL96SL','GT-WR4WXG6'] as $id)$this->assertStringContainsString($id,$html);
            $this->assertSame(1,substr_count($html,'id="culbertson-tracking-config"'));
            $this->assertStringContainsString('<noscript><iframe src="https://www.googletagmanager.com',$html);
            $this->assertStringNotContainsString('<script src="https://www.googletagmanager.com',$html);
        }
        $this->get('/admin/login')->assertOk()->assertDontSee('culbertson-tracking-config')->assertDontSee('tracking.js');
        $this->get('/cookie-policy/')->assertOk()->assertSee('Google Analytics and advertising measurement')->assertSee('does not require an analytics consent choice');
    }

    public function test_settings_are_database_driven_and_invalid_identifiers_fail_closed(): void
    {
        config(['tracking.enabled'=>true]);
        $row=FormDefinition::where('key','google-tracking')->firstOrFail();
        $data=$row->definition;$data['primaryAnalyticsId']='G-TEST123456';$row->update(['definition'=>$data]);
        $this->get('/')->assertOk()->assertSee('G-TEST123456');
        $data['containerId']='"><script>bad()</script>';$row->update(['definition'=>$data]);
        $this->get('/')->assertOk()->assertDontSee('culbertson-tracking-config')->assertDontSee('bad()',false);
    }

    public function test_supplied_snippets_execute_immediately_and_ignore_previous_consent(): void
    {
        config(['tracking.enabled'=>true]);
        foreach(['','culbertson_google_consent=deny-v1'] as $cookie){
            $html=$this->withHeader('Cookie',$cookie)->get('/')->assertOk()
                ->assertSee('id="culbertson-gtm-snippet">',false)
                ->assertSee('id="culbertson-ads-config">',false)
                ->assertSee('async src="https://www.googletagmanager.com/gtag/js?id=AW-18511592688"',false)
                ->assertSee('<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NPKN5562"',false)
                ->assertDontSee('type="text/plain"',false)->assertDontSee('tracking-choice')->getContent();
            $this->assertLessThan(strpos($html,'class="skip-link"'),strpos($html,'<noscript><iframe'));
        }
        $this->get('/thanks-for-contacting-us/thanks-auto-insurance-quote/')->assertOk()->assertDontSee('id="culbertson-gtm-snippet"',false)->assertSee('id="culbertson-ads-loader"',false);
    }
}
