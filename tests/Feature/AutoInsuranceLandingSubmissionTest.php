<?php
namespace Tests\Feature;

use App\Models\QuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutoInsuranceLandingSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials()->withSession([])->withCookie(config('session.cookie'), session()->getId());
    }

    public function test_final_contact_step_saves_a_completed_lead_and_returns_thank_you_receipt(): void
    {
        $submission = [
            'flow_id' => (string) Str::uuid(),
            'first_name' => 'Jamie',
            'last_name' => 'Driver',
            'email' => 'jamie@example.com',
            'phone' => '(239) 555-0123',
            'ownership' => 'lease',
            'insured' => 'Yes',
            'carrier' => 'State Farm',
            'zip' => '33907',
            'sms' => true,
            'landing_path' => '/auto-insurance/',
        ];

        $result = $this->postJson('/auto-insurance/submit', $submission)
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $reference = $result->json('reference');
        $lead = QuoteRequest::where('reference', $reference)->firstOrFail();
        $this->assertSame('completed', $lead->completion_status);
        $this->assertSame('Jamie Driver', $lead->payload['request']['name']);
        $this->assertSame('2395550123', $lead->payload['request']['phone']);
        $this->assertSame('Lease', $lead->payload['request']['vehicleOwnership']);
        $this->assertSame('State Farm', $lead->payload['request']['carrier']);
        $this->assertSame('33907', $lead->payload['request']['address']['zip']);
        $this->assertSame('/auto-insurance/', $lead->payload['landingPage']);
        $this->assertTrue($lead->payload['smsConsent']);
        $this->assertStringContainsString('/quote/thank-you?receipt=', $result->json('redirect'));

        $this->postJson('/auto-insurance/submit', $submission)
            ->assertCreated()
            ->assertJsonPath('reference', $reference);
        $this->assertDatabaseCount('quote_requests', 1);
    }

    public function test_landing_request_can_be_submitted_without_text_message_consent(): void
    {
        $result = $this->postJson('/auto-insurance/submit', [
            'flow_id' => (string) Str::uuid(),
            'first_name' => 'Taylor',
            'last_name' => 'Driver',
            'email' => 'taylor@example.com',
            'phone' => '2395550144',
            'ownership' => 'own',
            'insured' => 'No',
            'carrier' => 'Currently not insured',
            'zip' => '33907',
        ])->assertCreated();

        $lead = QuoteRequest::where('reference', $result->json('reference'))->firstOrFail();
        $this->assertFalse($lead->payload['smsConsent']);
        $this->assertSame('Currently not insured', $lead->payload['request']['carrier']);
    }
}
