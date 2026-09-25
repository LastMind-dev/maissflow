<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\LegacyManyChatFlowImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyManyChatFlowImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_archives_previous_versions_and_publishes_an_editable_legacy_flow(): void
    {
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        $user = User::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'admin@primaxonline.com.br',
            'role' => 'owner',
        ]);
        $automation = Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Atendimento principal',
            'status' => 'active',
            'trigger_type' => 'incoming_message',
            'created_by' => $user->id,
        ]);
        $oldGraph = [
            'schemaVersion' => 1,
            'nodes' => [[
                'id' => 'trigger',
                'type' => 'trigger',
                'position' => ['x' => 0, 'y' => 0],
                'data' => [],
            ]],
            'edges' => [],
        ];
        $oldPublished = $automation->versions()->create([
            'version' => 1,
            'status' => 'published',
            'graph' => $oldGraph,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);
        $automation->versions()->create([
            'version' => 2,
            'status' => 'draft',
            'graph' => $oldGraph,
            'created_by' => $user->id,
        ]);
        $automation->update(['published_version_id' => $oldPublished->id]);

        $published = app(LegacyManyChatFlowImporter::class)->import($user, $automation, true);

        $this->assertSame(3, $published->version);
        $this->assertSame('published', $published->status);
        $this->assertSame('manychat', $published->graph['source']['provider']);
        $this->assertSame('Newchatbot', $published->graph['source']['name']);
        $this->assertCount(68, $published->graph['nodes']);
        $this->assertCount(113, $published->graph['edges']);
        $this->assertSame('archived', $oldPublished->fresh()->status);
        $this->assertDatabaseHas('automation_versions', [
            'automation_id' => $automation->id,
            'version' => 4,
            'status' => 'draft',
        ]);
        $this->assertSame($published->id, $automation->fresh()->published_version_id);
    }
}
