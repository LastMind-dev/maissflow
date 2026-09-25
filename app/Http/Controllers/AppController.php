<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppChannel;
use App\Services\Actions\ActionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppController extends Controller
{
    public function show(): View
    {
        return view('app');
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaceId = $user->workspace_id;
        $channel = WhatsAppChannel::query()->where('workspace_id', $workspaceId)->first();

        return response()->json([
            'user' => $user->only(['name', 'email', 'role']),
            'workspace' => $user->workspace?->only(['public_id', 'name', 'slug', 'timezone']),
            'channel' => $channel ? $this->channelData($channel) : null,
            'flow_actions' => app(ActionRegistry::class)->describe(),
            'metrics' => [
                'automations' => Automation::query()->where('workspace_id', $workspaceId)->count(),
                'active_automations' => Automation::query()->where('workspace_id', $workspaceId)->where('status', 'active')->count(),
                'contacts' => Contact::query()->where('workspace_id', $workspaceId)->count(),
                'open_conversations' => Conversation::query()->where('workspace_id', $workspaceId)->whereIn('status', ['bot', 'human'])->count(),
                'unread' => Conversation::query()->where('workspace_id', $workspaceId)->sum('unread_count'),
                'messages_today' => Message::query()->where('workspace_id', $workspaceId)->whereDate('created_at', today())->count(),
            ],
            'recent_conversations' => Conversation::query()
                ->where('workspace_id', $workspaceId)
                ->with(['contact:id,public_id,name,phone_number', 'assignee:id,name'])
                ->latest('last_message_at')
                ->limit(6)
                ->get()
                ->map(fn (Conversation $conversation) => [
                    'public_id' => $conversation->public_id,
                    'contact' => $conversation->contact,
                    'status' => $conversation->status,
                    'unread_count' => $conversation->unread_count,
                    'last_message_at' => $conversation->last_message_at,
                    'assignee' => $conversation->assignee,
                ]),
        ]);
    }

    private function channelData(WhatsAppChannel $channel): array
    {
        return [
            'public_id' => $channel->public_id,
            'name' => $channel->name,
            'phone_number_id' => $channel->phone_number_id,
            'waba_id' => $channel->waba_id,
            'display_phone_number' => $channel->display_phone_number,
            'graph_version' => $channel->graph_version,
            'status' => $channel->status,
            'configured' => $channel->isConfigured(),
            'has_access_token' => filled($channel->access_token),
            'has_app_secret' => filled($channel->app_secret),
            'has_verify_token' => filled($channel->verify_token),
            'last_verified_at' => $channel->last_verified_at,
        ];
    }
}
