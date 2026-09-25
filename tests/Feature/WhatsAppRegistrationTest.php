<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WhatsAppChannel;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_registers_only_the_workspace_phone_without_exposing_the_pin(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);
        [$owner, $channel] = $this->ownerAndChannel();
        $pin = '482617';

        $response = $this->actingAs($owner)->postJson('/api/app/channel/register', ['pin' => $pin]);

        $response->assertOk()->assertJsonPath('message', 'Número registrado na Meta.');
        $this->assertStringNotContainsString($pin, $response->getContent());
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://graph.facebook.com/v25.0/'.$channel->phone_number_id.'/register'
            && $request['messaging_product'] === 'whatsapp'
            && $request['pin'] === $pin);
        $audit = AuditLog::query()->where('action', 'whatsapp_channel.registration_attempted')->sole();
        $this->assertTrue($audit->after['success']);
        $this->assertStringNotContainsString($pin, json_encode($audit->after));
    }

    public function test_registration_rejects_unauthorized_users_and_invalid_pins_without_calling_meta(): void
    {
        Http::fake();
        [$owner] = $this->ownerAndChannel();
        $manager = User::factory()->create(['workspace_id' => $owner->workspace_id, 'role' => 'manager']);
        $admin = User::factory()->create(['workspace_id' => $owner->workspace_id, 'role' => 'admin']);

        $this->postJson('/api/app/channel/register', ['pin' => '482617'])->assertUnauthorized();
        $this->actingAs($manager)->postJson('/api/app/channel/register', ['pin' => '482617'])->assertForbidden();
        $this->actingAs($admin)->postJson('/api/app/channel/register', ['pin' => '482617'])->assertForbidden();
        $this->actingAs($owner)->postJson('/api/app/channel/register', ['pin' => 'abcdef'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_meta_registration_failure_does_not_expose_credentials(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Registration denied', 'code' => 190, 'error_subcode' => 33],
        ], 400)]);
        [$owner] = $this->ownerAndChannel();

        $response = $this->actingAs($owner)->postJson('/api/app/channel/register', ['pin' => '482617']);

        $response->assertStatus(422)->assertJsonPath('meta.code', 190);
        $this->assertStringNotContainsString('482617', $response->getContent());
        $this->assertStringNotContainsString('access-token', $response->getContent());
    }

    public function test_invalid_pin_is_not_flashed_to_session(): void
    {
        [$owner] = $this->ownerAndChannel();

        $this->actingAs($owner)
            ->from('/app/settings')
            ->post('/api/app/channel/register', ['pin' => 'invalid-secret'])
            ->assertRedirect('/app/settings')
            ->assertSessionHasErrors('pin')
            ->assertSessionMissing('_old_input.pin');
    }

    private function ownerAndChannel(): array
    {
        $workspace = Workspace::create(['name' => 'Ouvidoria', 'slug' => 'ouvidoria']);
        $owner = User::factory()->create(['workspace_id' => $workspace->id, 'role' => 'owner']);
        $channel = WhatsAppChannel::create([
            'workspace_id' => $workspace->id,
            'name' => 'Ouvidoria',
            'phone_number_id' => '1352183974645624',
            'waba_id' => '2877390999306105',
            'graph_version' => 'v25.0',
            'access_token' => 'access-token',
            'app_secret' => 'app-secret',
            'verify_token' => 'verify-token-with-adequate-length',
            'status' => 'configured',
        ]);

        return [$owner, $channel];
    }
}
