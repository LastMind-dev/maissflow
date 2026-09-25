<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_save_publish_and_receive_a_new_draft(): void
    {
        $user = $this->manager();

        $created = $this->actingAs($user)->postJson('/api/app/automations', [
            'name' => 'Atendimento principal',
        ])->assertCreated()->json('data');

        $this->assertCount(68, $created['draft']['graph']['nodes']);
        $revision = $created['draft']['revision'];

        $graph = $created['draft']['graph'];
        $graph['nodes'][1]['data']['text'] = 'Mensagem revisada para publicação.';

        $this->actingAs($user)->putJson("/api/app/automations/{$created['public_id']}/graph", [
            'graph' => $graph,
            'expected_revision' => $revision,
        ])->assertOk()->assertJsonPath('data.revision', $revision + 1);

        $published = $this->actingAs($user)
            ->postJson("/api/app/automations/{$created['public_id']}/publish")
            ->assertOk()
            ->json('data');

        $this->assertSame('active', $published['status']);
        $this->assertSame(1, $published['published_version']);
        $this->assertSame(2, $published['draft']['version']);
        $this->assertDatabaseHas('automation_versions', ['automation_id' => Automation::first()->id, 'status' => 'published', 'version' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'automation.published']);
    }

    public function test_optimistic_revision_prevents_overwriting_another_editor(): void
    {
        $user = $this->manager();
        $created = $this->actingAs($user)->postJson('/api/app/automations', [
            'name' => 'Concorrência',
        ])->json('data');

        $payload = [
            'graph' => $created['draft']['graph'],
            'expected_revision' => $created['draft']['revision'],
        ];

        $this->actingAs($user)->putJson("/api/app/automations/{$created['public_id']}/graph", $payload)->assertOk();
        $this->actingAs($user)->putJson("/api/app/automations/{$created['public_id']}/graph", $payload)->assertConflict();
    }

    public function test_workspace_scope_blocks_cross_company_access(): void
    {
        $owner = $this->manager();
        $created = $this->actingAs($owner)->postJson('/api/app/automations', ['name' => 'Privada'])->json('data');

        $otherWorkspace = Workspace::create(['name' => 'Outra', 'slug' => 'outra']);
        $other = User::create([
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Outra Pessoa',
            'email' => 'other@example.com',
            'password' => 'secret',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($other)->getJson("/api/app/automations/{$created['public_id']}")->assertNotFound();
    }

    private function manager(): User
    {
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);

        return User::create([
            'workspace_id' => $workspace->id,
            'name' => 'Gestor',
            'email' => 'manager@example.com',
            'password' => 'secret',
            'role' => 'owner',
            'is_active' => true,
        ]);
    }
}
