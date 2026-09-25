<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationVersion;
use App\Services\AuditService;
use App\Services\AutomationPublisher;
use App\Services\DefaultFlowFactory;
use App\Services\FlowGraphValidator;
use App\Services\FlowSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutomationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $automations = Automation::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->with(['publishedVersion:id,automation_id,version,published_at'])
            ->withCount('versions')
            ->latest()
            ->get()
            ->map(fn (Automation $automation) => $this->summary($automation));

        return response()->json(['data' => $automations]);
    }

    public function store(
        Request $request,
        DefaultFlowFactory $factory,
        AuditService $audit,
    ): JsonResponse {
        $this->requireAutomationManager($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'blank' => ['sometimes', 'boolean'],
        ]);

        $automation = DB::transaction(function () use ($request, $validated, $factory): Automation {
            $automation = Automation::create([
                'workspace_id' => $request->user()->workspace_id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => 'draft',
                'trigger_type' => 'incoming_message',
                'created_by' => $request->user()->id,
            ]);

            $graph = ($validated['blank'] ?? false)
                ? [
                    'schemaVersion' => 1,
                    'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
                    'nodes' => [[
                        'id' => 'trigger_incoming',
                        'type' => 'trigger',
                        'position' => ['x' => 80, 'y' => 200],
                        'data' => ['label' => 'Quando o usuário enviar uma mensagem', 'triggerType' => 'incoming_message'],
                    ]],
                    'edges' => [],
                ]
                : $factory->make();

            $automation->versions()->create([
                'version' => 1,
                'revision' => 1,
                'status' => 'draft',
                'graph' => $graph,
                'created_by' => $request->user()->id,
            ]);

            return $automation;
        }, 3);

        $audit->record($request->user(), 'automation.created', $automation, null, $automation->only(['name', 'status']), $request);

        return response()->json(['data' => $this->detail($automation)], 201);
    }

    public function show(Request $request, string $automation): JsonResponse
    {
        return response()->json(['data' => $this->detail($this->findAutomation($request, $automation))]);
    }

    public function update(Request $request, string $automation, AuditService $audit): JsonResponse
    {
        $this->requireAutomationManager($request);
        $model = $this->findAutomation($request, $automation);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $before = $model->only(['name', 'description']);
        $model->update($validated);
        $audit->record($request->user(), 'automation.updated', $model, $before, $validated, $request);

        return response()->json(['data' => $this->detail($model)]);
    }

    public function saveGraph(
        Request $request,
        string $automation,
        FlowGraphValidator $validator,
        AuditService $audit,
    ): JsonResponse {
        $this->requireAutomationManager($request);
        $model = $this->findAutomation($request, $automation);
        $validated = $request->validate([
            'graph' => ['required', 'array'],
            'graph.nodes' => ['required', 'array', 'max:200'],
            'graph.edges' => ['required', 'array', 'max:500'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        $result = DB::transaction(function () use ($model, $validated, $validator): array {
            $draft = AutomationVersion::query()
                ->where('automation_id', $model->id)
                ->where('status', 'draft')
                ->latest('version')
                ->lockForUpdate()
                ->firstOrFail();

            if ($draft->revision !== (int) $validated['expected_revision']) {
                return ['conflict' => true, 'draft' => $draft];
            }

            $validation = $validator->validate($validated['graph']);
            $draft->update([
                'graph' => $validated['graph'],
                'validation_result' => $validation,
                'revision' => $draft->revision + 1,
            ]);

            return ['conflict' => false, 'draft' => $draft->fresh(), 'validation' => $validation];
        }, 3);

        if ($result['conflict']) {
            return response()->json([
                'message' => 'Este fluxo foi alterado em outra sessão. Recarregue antes de continuar.',
                'current_revision' => $result['draft']->revision,
            ], 409);
        }

        $audit->record(
            $request->user(),
            'automation.graph_saved',
            $model,
            null,
            ['version' => $result['draft']->version, 'revision' => $result['draft']->revision],
            $request,
        );

        return response()->json([
            'data' => [
                'revision' => $result['draft']->revision,
                'updated_at' => $result['draft']->updated_at,
                'validation' => $result['validation'],
            ],
        ]);
    }

    public function publish(
        Request $request,
        string $automation,
        AutomationPublisher $publisher,
        AuditService $audit,
    ): JsonResponse {
        $this->requireAutomationManager($request);
        $model = $this->findAutomation($request, $automation);
        $version = $publisher->publish($model, $request->user());
        $audit->record(
            $request->user(),
            'automation.published',
            $model,
            null,
            ['version' => $version->version],
            $request,
        );

        return response()->json([
            'message' => "Versão {$version->version} publicada com sucesso.",
            'data' => $this->detail($model->fresh()),
        ]);
    }

    public function duplicate(Request $request, string $automation, AuditService $audit): JsonResponse
    {
        $this->requireAutomationManager($request);
        $source = $this->findAutomation($request, $automation);
        $sourceDraft = $source->draftVersion() ?? $source->publishedVersion;
        abort_unless($sourceDraft, 422, 'A automação não possui uma versão para duplicar.');

        $copy = DB::transaction(function () use ($request, $source, $sourceDraft): Automation {
            $copy = Automation::create([
                'workspace_id' => $request->user()->workspace_id,
                'name' => $source->name.' — Cópia',
                'description' => $source->description,
                'status' => 'draft',
                'trigger_type' => $source->trigger_type,
                'trigger_config' => $source->trigger_config,
                'created_by' => $request->user()->id,
            ]);
            $copy->versions()->create([
                'version' => 1,
                'revision' => 1,
                'status' => 'draft',
                'graph' => $sourceDraft->graph,
                'created_by' => $request->user()->id,
            ]);

            return $copy;
        }, 3);

        $audit->record($request->user(), 'automation.duplicated', $copy, null, ['source' => $source->public_id], $request);

        return response()->json(['data' => $this->detail($copy)], 201);
    }

    public function simulate(
        Request $request,
        string $automation,
        FlowSimulator $simulator,
    ): JsonResponse {
        $model = $this->findAutomation($request, $automation);
        $validated = $request->validate(['inputs' => ['sometimes', 'array', 'max:30']]);
        $draft = $model->draftVersion() ?? $model->publishedVersion;
        abort_unless($draft, 422, 'A automação não possui fluxo.');

        try {
            return response()->json(['data' => $simulator->run($draft->graph, $validated['inputs'] ?? [])]);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['inputs' => $exception->getMessage()]);
        }
    }

    private function findAutomation(Request $request, string $publicId): Automation
    {
        return Automation::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function summary(Automation $automation): array
    {
        $draft = $automation->draftVersion();

        return [
            'public_id' => $automation->public_id,
            'name' => $automation->name,
            'description' => $automation->description,
            'status' => $automation->status,
            'trigger_type' => $automation->trigger_type,
            'versions_count' => $automation->versions_count ?? $automation->versions()->count(),
            'draft_revision' => $draft?->revision,
            'published_version' => $automation->publishedVersion?->version,
            'published_at' => $automation->publishedVersion?->published_at,
            'updated_at' => $automation->updated_at,
        ];
    }

    private function detail(Automation $automation): array
    {
        $automation->loadMissing('publishedVersion');
        $draft = $automation->draftVersion();

        return [
            ...$this->summary($automation),
            'draft' => $draft ? [
                'version' => $draft->version,
                'revision' => $draft->revision,
                'graph' => $draft->graph,
                'validation' => $draft->validation_result,
                'updated_at' => $draft->updated_at,
            ] : null,
        ];
    }
}
