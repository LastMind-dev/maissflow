<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_team_and_read_operational_health(): void
    {
        config(['queue.default' => 'database', 'queue.webhook_queue' => 'default']);
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        $owner = User::factory()->create([
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);

        $created = $this->actingAs($owner)->postJson('/api/app/team', [
            'name' => 'Atendente Um',
            'email' => 'atendente@example.com',
            'password' => 'senha-segura-123',
            'role' => 'agent',
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('data.email', 'atendente@example.com')
            ->assertJsonPath('data.role', 'agent');

        $memberId = $created->json('data.id');
        $this->actingAs($owner)->putJson("/api/app/team/{$memberId}", [
            'role' => 'admin',
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.role', 'admin');

        $this->actingAs($owner)
            ->getJson('/api/app/operations')
            ->assertOk()
            ->assertJsonPath('data.queue.connection', 'database')
            ->assertJsonPath('data.queue.webhook_queue', 'default')
            ->assertJsonFragment(['action' => 'team.member_created'])
            ->assertJsonFragment(['action' => 'team.member_updated']);
    }

    public function test_contacts_can_be_exported_as_utf8_csv(): void
    {
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        $owner = User::factory()->create([
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);
        Contact::create([
            'workspace_id' => $workspace->id,
            'wa_id' => '5517999999999',
            'phone_number' => '5517999999999',
            'name' => 'Cliente Exportado',
            'email' => 'cliente@example.com',
        ]);

        $response = $this->actingAs($owner)->get('/api/app/contacts/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Cliente Exportado', $response->streamedContent());
    }

    public function test_webhook_job_uses_the_plesk_default_queue(): void
    {
        config(['queue.webhook_queue' => 'webhooks']);

        $job = new ProcessWhatsAppWebhook(123);

        $this->assertSame('default', $job->queue);
        $this->assertSame(5, $job->tries);
    }
}
