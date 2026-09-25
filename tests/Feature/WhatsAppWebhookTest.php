<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsAppChannel;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_verification_requires_the_configured_token(): void
    {
        $channel = $this->channel();

        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-token-with-adequate-length&hub_challenge=12345')
            ->assertOk()
            ->assertSeeText('12345');

        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')
            ->assertForbidden();
    }

    public function test_signed_webhook_is_accepted_once_and_invalid_signature_is_rejected(): void
    {
        Queue::fake();
        $channel = $this->channel();
        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => $channel->phone_number_id],
                        'messages' => [],
                    ],
                ]],
            ]],
        ];
        $raw = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $raw, 'application-secret');

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $raw)->assertOk();

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $raw)->assertOk();

        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushed(ProcessWhatsAppWebhook::class, 1);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ], $raw)->assertUnauthorized();
    }

    private function channel(): WhatsAppChannel
    {
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);

        return WhatsAppChannel::create([
            'workspace_id' => $workspace->id,
            'name' => 'Principal',
            'phone_number_id' => '123456789',
            'waba_id' => '998877',
            'access_token' => 'access-token',
            'app_secret' => 'application-secret',
            'verify_token' => 'verify-token-with-adequate-length',
            'graph_version' => 'v25.0',
            'status' => 'configured',
        ]);
    }
}
