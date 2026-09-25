<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LegacyManyChatFlowImporter
{
    public function __construct(
        private readonly DefaultFlowFactory $factory,
        private readonly FlowGraphValidator $validator,
    ) {}

    public function import(User $user, ?Automation $automation = null, bool $force = false): AutomationVersion
    {
        if (! $user->workspace_id) {
            throw new RuntimeException('O usuário informado não pertence a um workspace.');
        }

        if ($automation && $automation->workspace_id !== $user->workspace_id) {
            throw new RuntimeException('A automação informada pertence a outro workspace.');
        }

        $graph = $this->factory->make();
        $validation = $this->validator->validate($graph);
        if (! $validation['valid']) {
            throw ValidationException::withMessages(['graph' => $validation['errors']]);
        }

        return DB::transaction(function () use ($user, $automation, $force, $graph, $validation): AutomationVersion {
            $locked = $automation
                ? Automation::query()->whereKey($automation->id)->lockForUpdate()->firstOrFail()
                : Automation::query()
                    ->where('workspace_id', $user->workspace_id)
                    ->where('name', 'Atendimento principal')
                    ->lockForUpdate()
                    ->first();

            if (! $locked) {
                $locked = Automation::create([
                    'workspace_id' => $user->workspace_id,
                    'name' => 'Atendimento principal',
                    'description' => 'Fluxo legado Newchatbot importado integralmente do ManyChat.',
                    'status' => 'draft',
                    'trigger_type' => 'incoming_message',
                    'created_by' => $user->id,
                ]);
            }

            $published = $locked->publishedVersion;
            if (! $force && $published && $published->graph === $graph) {
                return $published;
            }

            $locked->versions()->whereIn('status', ['draft', 'published'])->update(['status' => 'archived']);

            $nextVersion = ((int) $locked->versions()->max('version')) + 1;
            $published = $locked->versions()->create([
                'version' => $nextVersion,
                'revision' => 1,
                'status' => 'published',
                'graph' => $graph,
                'validation_result' => $validation,
                'created_by' => $user->id,
                'published_by' => $user->id,
                'published_at' => now(),
            ]);

            $locked->versions()->create([
                'version' => $nextVersion + 1,
                'revision' => 1,
                'status' => 'draft',
                'graph' => $graph,
                'validation_result' => $validation,
                'created_by' => $user->id,
            ]);

            $locked->update([
                'description' => 'Fluxo legado Newchatbot importado integralmente do ManyChat.',
                'status' => 'active',
                'trigger_type' => 'incoming_message',
                'published_version_id' => $published->id,
            ]);

            return $published->fresh();
        }, 3);
    }
}
