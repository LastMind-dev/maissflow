<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\AuditService;
use App\Services\ConversationRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:bot,human,closed'],
            'filter' => ['nullable', 'in:all,mine,unread'],
        ]);

        $query = Conversation::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->with([
                'contact:id,public_id,name,phone_number,last_seen_at',
                'assignee:id,name',
                'lastMessage' => fn ($messages) => $messages->select([
                    'messages.id',
                    'messages.conversation_id',
                    'messages.direction',
                    'messages.type',
                    'messages.content',
                    'messages.created_at',
                ]),
            ])
            ->when($validated['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when(($validated['filter'] ?? 'all') === 'mine', fn ($builder) => $builder
                ->where('assigned_to', $request->user()->id))
            ->when(($validated['filter'] ?? 'all') === 'unread', fn ($builder) => $builder
                ->where('unread_count', '>', 0))
            ->when($validated['search'] ?? null, function ($builder, $search): void {
                $builder->whereHas('contact', fn ($contacts) => $contacts
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%"));
            })
            ->latest('last_message_at');

        return response()->json(['data' => $query->limit(100)->get()->map(fn ($item) => $this->conversationData($item))]);
    }

    public function show(Request $request, string $conversation): JsonResponse
    {
        $model = $this->findConversation($request, $conversation);
        $model->update(['unread_count' => 0]);
        $model->load(['contact', 'channel', 'assignee', 'messages' => fn ($query) => $query->latest()->limit(200)]);

        return response()->json([
            'data' => [
                ...$this->conversationData($model),
                'channel' => $model->channel->only(['name', 'display_phone_number']),
                'window_open' => (bool) $model->customer_service_window_expires_at?->isFuture(),
                'can_reply' => $model->status === 'human'
                    && (bool) $model->customer_service_window_expires_at?->isFuture(),
                'window_expires_at' => $model->customer_service_window_expires_at,
                'messages' => $model->messages->sortBy('created_at')->values()->map(fn ($message) => [
                    'id' => $message->id,
                    'direction' => $message->direction,
                    'type' => $message->type,
                    'status' => $message->status,
                    'content' => $message->content,
                    'error' => $message->error,
                    'created_at' => $message->created_at,
                ]),
            ],
        ]);
    }

    public function reply(
        Request $request,
        string $conversation,
        ConversationRuntime $runtime,
    ): JsonResponse {
        $model = $this->findConversation($request, $conversation);
        $validated = $request->validate(['text' => ['required', 'string', 'max:4096']]);

        try {
            $message = $runtime->sendManualReply($model->load(['contact', 'channel']), $validated['text'], $request->user()->id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $message], 201);
    }

    public function take(Request $request, string $conversation, AuditService $audit): JsonResponse
    {
        $model = $this->findConversation($request, $conversation);
        $before = $model->only(['status', 'assigned_to']);
        $model->update([
            'assigned_to' => $request->user()->id,
            'status' => 'human',
            'closed_at' => null,
        ]);
        $audit->record($request->user(), 'conversation.taken', $model, $before, [
            'status' => 'human',
            'assigned_to' => $request->user()->id,
        ], $request);

        return response()->json(['data' => $this->conversationData($model->fresh(['contact', 'assignee', 'lastMessage']))]);
    }

    public function mode(
        Request $request,
        string $conversation,
        AuditService $audit,
    ): JsonResponse {
        $model = $this->findConversation($request, $conversation);
        $validated = $request->validate(['status' => ['required', 'in:bot,human,closed']]);
        $before = $model->only(['status', 'assigned_to', 'closed_at']);

        DB::transaction(function () use ($model, $request, $validated): void {
            $status = $validated['status'];
            $model->update([
                'status' => $status,
                'assigned_to' => $status === 'human' ? $request->user()->id : null,
                'closed_at' => $status === 'closed' ? now() : null,
            ]);

            if ($status === 'closed') {
                $model->executions()
                    ->whereIn('status', ['running', 'waiting'])
                    ->update(['status' => 'completed', 'completed_at' => now()]);
            }
        }, 3);

        $audit->record($request->user(), 'conversation.mode_changed', $model, $before, [
            'status' => $validated['status'],
            'assigned_to' => $validated['status'] === 'human' ? $request->user()->id : null,
            'closed_at' => $validated['status'] === 'closed' ? now()->toIso8601String() : null,
        ], $request);

        return response()->json(['data' => $this->conversationData($model->fresh(['contact', 'assignee', 'lastMessage']))]);
    }

    private function findConversation(Request $request, string $publicId): Conversation
    {
        return Conversation::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function conversationData(Conversation $conversation): array
    {
        return [
            'public_id' => $conversation->public_id,
            'contact' => $conversation->contact,
            'status' => $conversation->status,
            'unread_count' => $conversation->unread_count,
            'last_message_at' => $conversation->last_message_at,
            'last_message' => $conversation->lastMessage,
            'assignee' => $conversation->assignee,
        ];
    }
}
