<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Models\WhatsAppChannel;
use App\Services\ConversationRuntime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use FoundationQueueable, InteractsWithQueue;

    public int $tries = 5;

    public array $backoff = [5, 30, 120, 300];

    public function __construct(public readonly int $webhookEventId)
    {
        $this->onQueue('default');
    }

    public function handle(ConversationRuntime $runtime): void
    {
        $event = WebhookEvent::query()->findOrFail($this->webhookEventId);
        if ($event->status === 'processed') {
            return;
        }

        $event->increment('attempts');
        $channel = $event->whatsapp_channel_id
            ? WhatsAppChannel::query()->find($event->whatsapp_channel_id)
            : null;

        if (! $channel) {
            $event->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }

        try {
            foreach ($event->payload['entry'] ?? [] as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    $value = $change['value'] ?? [];
                    $runtime->applyStatuses($value['statuses'] ?? []);
                    foreach ($value['messages'] ?? [] as $message) {
                        $runtime->ingestInbound($channel, $value, $message);
                    }
                }
            }

            $event->update(['status' => 'processed', 'processed_at' => now(), 'error_message' => null]);
        } catch (Throwable $exception) {
            $event->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage(), 0, 4000)]);
            throw $exception;
        }
    }
}
