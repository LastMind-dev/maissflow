<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WebhookEvent;
use App\Models\WhatsAppChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $providedToken = (string) $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge');

        if ($mode !== 'subscribe' || $providedToken === '' || $challenge === '') {
            return response('Parâmetros de verificação inválidos.', 400);
        }

        $matches = WhatsAppChannel::query()
            ->whereNotNull('verify_token')
            ->get()
            ->contains(fn (WhatsAppChannel $channel) => hash_equals((string) $channel->verify_token, $providedToken));

        return $matches
            ? response($challenge, 200)->header('Content-Type', 'text/plain')
            : response('Token de verificação inválido.', 403);
    }

    public function handle(Request $request): Response
    {
        $rawPayload = $request->getContent();
        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return response('Payload inválido.', 400);
        }

        $phoneNumberId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');
        $channel = $phoneNumberId
            ? WhatsAppChannel::query()->where('phone_number_id', $phoneNumberId)->first()
            : null;

        if (! $channel) {
            return response('EVENT_IGNORED', 202);
        }

        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $rawPayload, (string) $channel->app_secret);

        if (! filled($channel->app_secret) || ! hash_equals($expected, $signature)) {
            return response('Assinatura inválida.', 401);
        }

        $eventId = hash('sha256', $rawPayload);
        $event = WebhookEvent::query()->firstOrCreate(
            ['event_id' => $eventId],
            [
                'whatsapp_channel_id' => $channel->id,
                'status' => 'received',
                'payload' => $payload,
                'received_at' => now(),
            ],
        );

        if ($event->wasRecentlyCreated) {
            ProcessWhatsAppWebhook::dispatch($event->id);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
