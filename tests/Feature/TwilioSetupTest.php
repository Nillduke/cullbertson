<?php

namespace Tests\Feature;

use App\Models\TwilioSetting;
use App\Models\User;
use App\Services\TwilioGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwilioSetupTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT = 'AC11111111111111111111111111111111';
    private const TOKEN = 'synthetic-test-token-never-a-real-secret';

    private function setupAdminAndTwilio(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        TwilioSetting::current()->update([
            'enabled' => true,
            'sms_enabled' => true,
            'voice_enabled' => true,
            'account_sid' => self::ACCOUNT,
            'auth_token' => self::TOKEN,
            'from_number' => '+12395550100',
            'voice_agent_number' => '+12395550101',
        ]);
    }

    public function test_settings_page_offers_test_sms_and_voice_actions(): void
    {
        $this->setupAdminAndTwilio();

        $this->get('/admin/integrations/twilio')
            ->assertOk()
            ->assertSee('Send a test SMS or call')
            ->assertSee('Send test SMS')
            ->assertSee('Place test call')
            ->assertSee('Do not use a customer’s number');
    }

    public function test_admin_can_send_test_sms_and_place_test_call_to_their_own_number(): void
    {
        $this->setupAdminAndTwilio();
        $this->mock(TwilioGateway::class, function ($mock) {
            $mock->shouldReceive('testSms')
                ->once()
                ->withArgs(fn ($settings, $to) => $to === '+12395550123')
                ->andReturn(['sid' => 'SM22222222222222222222222222222222', 'status' => 'queued', 'from' => '+12395550100']);
            $mock->shouldReceive('testCall')
                ->once()
                ->withArgs(fn ($settings, $to) => $to === '+12395550123')
                ->andReturn(['sid' => 'CA33333333333333333333333333333333', 'status' => 'queued']);
        });

        $this->post('/admin/integrations/twilio/test', ['channel' => 'sms', 'test_phone' => '(239) 555-0123'])
            ->assertSessionHas('twilio_test_result', fn ($message) => str_contains($message, 'Test SMS queued') && str_contains($message, 'SM22222222222222222222222222222222'));
        $this->post('/admin/integrations/twilio/test', ['channel' => 'voice', 'test_phone' => '239-555-0123'])
            ->assertSessionHas('twilio_test_result', fn ($message) => str_contains($message, 'Test voice call started') && str_contains($message, 'CA33333333333333333333333333333333'));
    }
}
