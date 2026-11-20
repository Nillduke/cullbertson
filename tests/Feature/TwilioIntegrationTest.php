<?php

namespace Tests\Feature;

use App\Models\{User, QuoteRequest, ContactEnquiry, InboxConversation, InboxMessage, TwilioSetting, TwilioDelivery};
use App\Services\{TwilioGateway, TwilioCommunications};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Twilio\Security\RequestValidator;
use Tests\TestCase;

class TwilioIntegrationTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;
    protected $seeder = \Database\Seeders\ContentSeeder::class;
    private const TOKEN = 'synthetic-test-token-never-a-real-secret';
    private const ACCOUNT = 'AC11111111111111111111111111111111';
    private const SMS = 'SM22222222222222222222222222222222';
    private const CALL = 'CA33333333333333333333333333333333';

    private function setupConnection(): TwilioSetting
    {
        config(['app.url' => 'https://example.com']);
        return tap(TwilioSetting::current(), fn ($s) => $s->update(['enabled' => true, 'sms_enabled' => true, 'voice_enabled' => true, 'account_sid' => self::ACCOUNT, 'auth_token' => self::TOKEN, 'from_number' => '+12395550100', 'voice_agent_number' => '+12395550101']));
    }
    private function admin(): void { $this->actingAs(User::factory()->create(['is_admin' => true])); }
    private function lead(): QuoteRequest
    {
        return QuoteRequest::withoutEvents(fn () => QuoteRequest::create(['reference' => 'TWILIO-TEST', 'service' => 'auto', 'payload' => ['request' => ['email' => 'person@example.com', 'phone' => '(239) 555-0102']]]));
    }
    private function signed(string $path, array $data)
    {
        $data['AccountSid'] = self::ACCOUNT;
        $signature = (new RequestValidator(self::TOKEN))->computeSignature(TwilioCommunications::publicUrl($path), $data);
        return $this->post($path, $data, ['X-Twilio-Signature' => $signature]);
    }
    private function smsData(string $body = 'Hello'): array
    {
        return ['MessageSid' => self::SMS, 'From' => '+12395550102', 'To' => '+12395550100', 'Body' => $body];
    }
    public function test_settings_are_admin_only_and_secret_is_encrypted_and_never_flashed(): void
    {
        $this->get('/admin/integrations/twilio')->assertRedirect('/admin/login');
        $this->admin(); $this->setupConnection();
        $this->get('/admin/integrations/twilio')->assertOk()->assertDontSee(self::TOKEN);
        $this->assertNotSame(self::TOKEN, TwilioSetting::current()->getRawOriginal('auth_token'));
        $this->post('/admin/integrations/twilio', ['enabled' => 1, 'account_sid' => 'bad', 'auth_token' => self::TOKEN])->assertSessionHasErrors('account_sid')->assertSessionMissing('_old_input.auth_token');
        $this->post('/admin/integrations/twilio', ['account_sid' => self::ACCOUNT, 'auth_token' => ''])->assertSessionHas('success');
        $this->assertSame(self::TOKEN, TwilioSetting::current()->auth_token);
        $this->mock(TwilioGateway::class, function ($mock) { $mock->shouldReceive('check')->once()->andReturn(['status' => 'active', 'type' => 'Full']); $mock->shouldNotReceive('sms'); $mock->shouldNotReceive('call'); });
        $this->post('/admin/integrations/twilio/connection')->assertSessionHas('success');
    }
    public function test_outbound_sms_is_normalized_idempotent_and_delivery_does_not_regress(): void
    {
        $this->admin(); $this->setupConnection(); $lead = $this->lead();
        $this->mock(TwilioGateway::class, fn ($mock) => $mock->shouldReceive('sms')->once()->withArgs(fn ($s, $to, $body, $callback) => $to === '+12395550102' && $body === 'Your quote is ready' && str_starts_with($callback, 'https://example.com/webhooks/twilio/status/'))->andReturn(['sid' => self::SMS, 'status' => 'queued', 'from' => '+12395550100']));
        $data = ['channel' => 'sms', 'body' => 'Your quote is ready', 'delivery_action' => 'send', 'client_token' => (string) Str::uuid()];
        $url = '/admin/inbox/quotes/'.$lead->id.'/messages';
        $this->post($url, $data)->assertSessionHas('success'); $this->post($url, $data)->assertSessionHas('success');
        $this->assertDatabaseCount('twilio_deliveries', 1); $row = TwilioDelivery::firstOrFail();
        $this->signed('/webhooks/twilio/status/'.$row->id, ['MessageSid' => self::SMS, 'MessageStatus' => 'delivered'])->assertNoContent();
        $this->signed('/webhooks/twilio/status/'.$row->id, ['MessageSid' => self::SMS, 'MessageStatus' => 'sent'])->assertNoContent();
        $this->assertSame('delivered', $row->fresh()->status);
    }
    public function test_drafts_disabled_channels_and_dnd_never_send(): void
    {
        $this->admin(); $s = $this->setupConnection(); $lead = $this->lead();
        $this->mock(TwilioGateway::class, function ($mock) { $mock->shouldNotReceive('sms'); $mock->shouldNotReceive('call'); });
        $url = '/admin/inbox/quotes/'.$lead->id;
        $data = ['channel' => 'sms', 'body' => 'Saved only', 'client_token' => (string) Str::uuid()];
        $this->post($url.'/messages', $data)->assertSessionHas('success');
        $this->assertDatabaseCount('twilio_deliveries', 0);
        $this->post($url.'/manage', ['sms_dnd' => 1, 'voice_dnd' => 1]);
        $this->post($url.'/messages', $data + ['delivery_action' => 'send'])->assertSessionHasErrors('body');
        $this->post($url.'/call', ['client_token' => (string) Str::uuid()])->assertSessionHasErrors('body');
        $s->update(['enabled' => false]); $this->signed('/webhooks/twilio/sms', $this->smsData())->assertStatus(503);
    }
    public function test_signed_incoming_sms_deduplicates_links_lead_and_handles_opt_out(): void
    {
        $this->setupConnection(); $lead = $this->lead();
        $this->post('/webhooks/twilio/sms', $this->smsData())->assertForbidden();
        $this->signed('/webhooks/twilio/sms', $this->smsData('STOP'))->assertOk();
        $this->signed('/webhooks/twilio/sms', $this->smsData('STOP'))->assertOk();
        $this->assertDatabaseCount('twilio_deliveries', 1); $c = InboxConversation::firstOrFail();
        $this->assertSame($lead->id, $c->source_id); $this->assertTrue($c->sms_dnd); $this->assertNull($c->read_at);
        $data = $this->smsData('START'); $data['MessageSid'] = 'SM44444444444444444444444444444444';
        $this->signed('/webhooks/twilio/sms', $data)->assertOk(); $this->assertFalse($c->fresh()->sms_dnd);
        $data['MessageSid'] = 'SM55555555555555555555555555555555'; $data['From'] = '+12395550199';
        $this->signed('/webhooks/twilio/sms', $data)->assertOk();
        $this->assertSame('twilio', ContactEnquiry::firstOrFail()->payload['origin']);
    }
    public function test_voice_rings_agency_first_and_only_connects_customer_after_pressing_one(): void
    {
        $this->admin(); $this->setupConnection(); $lead = $this->lead();
        $this->mock(TwilioGateway::class, fn ($mock) => $mock->shouldReceive('call')->once()->withArgs(fn ($s, $xml, $callback) => $s->voice_agent_number === '+12395550101' && str_contains($xml, '<Gather') && !str_contains($xml, '<Dial') && str_ends_with($callback, '?leg=agent'))->andReturn(['sid' => self::CALL, 'status' => 'queued']));
        $data = ['client_token' => (string) Str::uuid()]; $url = '/admin/inbox/quotes/'.$lead->id.'/call';
        $this->post($url, $data)->assertSessionHas('success'); $this->post($url, $data)->assertSessionHas('success');
        $row = TwilioDelivery::firstOrFail(); $path = '/webhooks/twilio/connect/'.$row->id;
        $this->signed($path, ['CallSid' => self::CALL, 'Digits' => '0'])->assertOk()->assertSee('Hangup')->assertDontSee('<Dial', false);
        $this->signed($path, ['CallSid' => self::CALL, 'Digits' => '1'])->assertOk()->assertSee('+12395550102', false)->assertSee('<Dial', false);
        $child = 'CA66666666666666666666666666666666';
        $this->signed('/webhooks/twilio/status/'.$row->id.'?leg=customer', ['CallSid' => $child, 'ParentCallSid' => self::CALL, 'CallStatus' => 'completed', 'CallDuration' => 15])->assertNoContent();
        $this->signed('/webhooks/twilio/status/'.$row->id.'?leg=agent', ['CallSid' => self::CALL, 'CallStatus' => 'completed'])->assertNoContent();
        $this->assertSame('completed', $row->fresh()->status); $this->assertSame(15, $row->fresh()->duration);
        $row->conversation->update(['voice_dnd' => true]);
        $this->signed($path, ['CallSid' => self::CALL, 'Digits' => '1'])->assertOk()->assertDontSee('<Dial', false);
    }
    public function test_incoming_call_forwards_to_agency_and_records_only_once(): void
    {
        $this->setupConnection(); $this->lead();
        $data = ['CallSid' => self::CALL, 'From' => '+12395550102', 'To' => '+12395550100'];
        $this->signed('/webhooks/twilio/voice', $data)->assertOk()->assertSee('+12395550101', false);
        $this->signed('/webhooks/twilio/voice', $data)->assertOk();
        $this->assertDatabaseCount('twilio_deliveries', 1); $this->assertDatabaseCount('inbox_messages', 1);
    }
}
