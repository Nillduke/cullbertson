<?php

namespace Tests\Feature;

use App\Models\{ContactEnquiry, FormDefinition, Page, SeoProfile, User};
use Database\Seeders\{ContentSeeder, FormInformationSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormInformationTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;
    protected $seeder = ContentSeeder::class;

    public function test_footer_appears_only_on_quote_workspaces_and_remains_editable_without_changing_consent(): void
    {
        foreach (['/get-a-quote/', '/get-a-quote/?service=home', '/auto-quote/'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            preg_match_all('~<form\b~i', $html, $forms);
            preg_match_all('~class="form-info(?: form-info--compact)?"~', $html, $footers);
            $this->assertNotEmpty($forms[0], $path);
            $this->assertCount(1, $footers[0], $path);
            $this->assertStringContainsString('Powered by', $html);
            $this->assertStringContainsString('ESIGN/electronic signature', $html);
            $this->assertStringContainsString('Message and data rates may apply.', $html);
            $this->assertStringContainsString('SMS Terms of Service', $html);
            $this->assertStringNotContainsString('Jill Owens', $html);
            $this->assertStringNotContainsString('Goal Platform', $html);
            $this->assertStringNotContainsString('California Privacy Choices', $html);
            if (in_array($path, ['/auto-quote/', '/get-a-quote/'])) $this->assertGreaterThan(strpos($html, '</main>'), strpos($html, 'class="form-info"'));
        }
        foreach (['/', '/contact-us/', '/recruit/', '/securely-send-pictures-documents-to-us/', '/classic-collector-car-insurance-in-florida/', '/privacy-choices/', '/insurance/homeowners-insurance/'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('class="form-info',false)->assertDontSee('ESIGN/electronic signature');
        }
        $this->get('/admin/login')->assertOk()->assertDontSee('class="form-info',false);
        $this->getJson('/forms/services')->assertOk()->assertJsonStructure(['services']);
        $data=FormDefinition::data('form-information');
        $data['powered']='Website provided by';
        FormDefinition::where('key','form-information')->first()->update(['definition'=>$data]);
        $this->get('/auto-quote/')->assertSee('Website provided by');
        $consent=FormDefinition::data('quote-progress-copy');
        $this->assertStringContainsString('Optional:', $consent['sms']);
        $gzip=$this->withHeader('Accept-Encoding','gzip')->get('/auto-quote/')->assertHeader('Content-Encoding','gzip');
        $this->assertStringContainsString('Website provided by',gzdecode($gzip->getContent()));
    }

    public function test_policy_links_resolve_and_existing_privacy_metadata_is_preserved(): void
    {
        $before=SeoProfile::where('url_path','/privacy-policy-2/')->first()->fields;
        $sections=Page::where('slug','privacy-policy-2')->first()->sections()->count();
        (new FormInformationSeeder)->run();
        $this->assertSame($before,SeoProfile::where('url_path','/privacy-policy-2/')->first()->fields);
        $this->assertSame($sections,Page::where('slug','privacy-policy-2')->first()->sections()->count());
        foreach(FormDefinition::data('form-information')['links'] as $link) {
            $response=$this->get($link['url'])->assertOk();
            $this->assertSame(1,substr_count($response->getContent(),'<h1'));
        }
        $this->get('/privacy-policy/')->assertRedirect('/privacy-policy-2/')->assertStatus(301);
        $this->get('/ccpa-opt-out/')->assertRedirect('/do-not-sell-my-personal-information/')->assertStatus(301);
        $this->get('/privacy-policy-2/')->assertSee('id="cookies-and-technologies"',false)->assertSee('id="privacy-choices"',false);
        $this->get('/privacy-choices/')->assertSee('action="'.url('/privacy-choices/request').'"',false);
        $this->get('/terms-of-use/')->assertSee('Electronic signatures and communications');
        $this->get('/sms-terms-of-service/')->assertSee('HELP and STOP');
    }

    public function test_privacy_requests_require_authority_and_appear_in_the_existing_admin_inbox(): void
    {
        $data=['name'=>'Synthetic Privacy Visitor','email'=>'privacy-test@example.com','request_type'=>'do-not-sell','message'=>'Please review my sharing preference.','authorized'=>true];
        $this->post('/privacy-choices/request',array_diff_key($data,['authorized'=>true]))->assertSessionHasErrors('authorized');
        $this->post('/privacy-choices/request',array_merge($data,['request_type'=>'unsupported']))->assertSessionHasErrors('request_type');
        $this->post('/privacy-choices/request',array_merge($data,['company_website'=>'spam']))->assertSessionHasErrors('company_website');
        $this->assertDatabaseCount('contact_enquiries',0);
        $this->post('/privacy-choices/request',$data)->assertRedirect(url('/privacy-choices/').'#privacy-request')->assertSessionHas('privacy_reference');
        $this->get('/privacy-choices/')->assertSee('Your privacy request has been received.');
        $entry=ContactEnquiry::firstOrFail();
        $this->assertStringStartsWith('PRV-',$entry->reference);
        $this->assertSame('do-not-sell',$entry->payload['requestType']);
        $this->assertFalse($entry->payload['marketingConsent']);
        $this->assertSame('Privacy request',$entry->payload['topic']);
        $admin=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($admin)->get('/admin/inbox/contacts/'.$entry->id)->assertOk()->assertSee('privacy-test@example.com')->assertSee('Please review my sharing preference.');
    }
}
