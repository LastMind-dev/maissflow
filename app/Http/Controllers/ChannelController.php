<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppChannel;
use App\Services\AuditService;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function update(Request $request, AuditService $audit): JsonResponse
    {
        $this->requireAutomationManager($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone_number_id' => ['nullable', 'string', 'max:100'],
            'waba_id' => ['nullable', 'string', 'max:100'],
            'display_phone_number' => ['nullable', 'string', 'max:40'],
            'graph_version' => ['required', 'regex:/^v\\d+\\.0$/'],
            'access_token' => ['nullable', 'string', 'max:4096'],
            'app_secret' => ['nullable', 'string', 'max:512'],
            'verify_token' => ['nullable', 'string', 'min:24', 'max:255'],
        ]);

        $channel = WhatsAppChannel::query()->firstOrNew([
            'workspace_id' => $request->user()->workspace_id,
        ]);
        $before = $channel->exists ? $channel->only([
            'name', 'phone_number_id', 'waba_id', 'display_phone_number', 'graph_version', 'status',
        ]) : null;

        foreach (['access_token', 'app_secret', 'verify_token'] as $secret) {
            if (blank($validated[$secret] ?? null)) {
                unset($validated[$secret]);
            }
        }

        $channel->fill($validated);
        $channel->status = $channel->isConfigured() ? 'configured' : 'disconnected';
        $channel->save();

        $audit->record(
            $request->user(),
            'whatsapp_channel.updated',
            $channel,
            $before,
            $channel->only(['name', 'phone_number_id', 'waba_id', 'display_phone_number', 'graph_version', 'status']),
            $request,
        );

        return response()->json(['data' => $this->data($channel)]);
    }

    public function test(Request $request, WhatsAppCloudApiService $api, AuditService $audit): JsonResponse
    {
        $this->requireAutomationManager($request);
        $channel = WhatsAppChannel::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->firstOrFail();
        $result = $api->testConnection($channel);

        $channel->update([
            'status' => $result['success'] ? 'connected' : 'error',
            'last_verified_at' => $result['success'] ? now() : $channel->last_verified_at,
        ]);
        $audit->record($request->user(), 'whatsapp_channel.connection_tested', $channel, null, [
            'success' => $result['success'],
            'status' => $result['status'],
        ], $request);

        return response()->json([
            'message' => $result['success'] ? 'Conexão com a Meta validada.' : 'A Meta rejeitou a conexão.',
            'data' => $this->data($channel),
            'meta' => $result['success'] ? $result['data'] : ['error' => $result['error']],
        ], $result['success'] ? 200 : 422);
    }

    public function register(Request $request, WhatsAppCloudApiService $api, AuditService $audit): JsonResponse
    {
        abort_unless($request->user()->role === 'owner', 403);

        $validated = $request->validate([
            'pin' => ['required', 'regex:/^[0-9]{6}$/'],
        ]);
        $channel = WhatsAppChannel::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->firstOrFail();
        $result = $api->registerPhoneNumber($channel, $validated['pin']);

        $audit->record($request->user(), 'whatsapp_channel.registration_attempted', $channel, null, [
            'success' => $result['success'],
            'http_status' => $result['status'],
            'meta_error_code' => $result['code'],
            'meta_error_subcode' => $result['subcode'],
        ], $request);

        return response()->json([
            'message' => $result['success']
                ? 'Número registrado na Meta.'
                : 'A Meta não registrou o número. Confira a permissão do token, a verificação do telefone e o PIN.',
            'meta' => $result['success'] ? null : [
                'code' => $result['code'],
                'subcode' => $result['subcode'],
            ],
        ], $result['success'] ? 200 : 422);
    }

    private function data(WhatsAppChannel $channel): array
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
