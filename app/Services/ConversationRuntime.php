<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\FlowExecution;
use App\Models\Message;
use App\Models\WhatsAppChannel;
use App\Services\Actions\ActionRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ConversationRuntime
{
    public function __construct(
        private readonly WhatsAppCloudApiService $whatsApp,
        private readonly FlowMessageFormatter $messageFormatter,
        private readonly ActionRegistry $actions,
    ) {}

    public function ingestInbound(WhatsAppChannel $channel, array $value, array $inbound): void
    {
        $waId = (string) ($inbound['from'] ?? '');
        $messageId = (string) ($inbound['id'] ?? '');

        if ($waId === '' || $messageId === '') {
            return;
        }

        [$conversation, $created] = DB::transaction(function () use ($channel, $value, $inbound, $waId, $messageId): array {
            $profileName = data_get($value, 'contacts.0.profile.name');
            $contact = Contact::query()->firstOrNew([
                'workspace_id' => $channel->workspace_id,
                'wa_id' => $waId,
            ]);
            $contact->phone_number = $waId;
            $contact->last_seen_at = now();
            if ($profileName) {
                $contact->name = $profileName;
            }
            $contact->save();

            $conversation = Conversation::query()->firstOrCreate(
                ['whatsapp_channel_id' => $channel->id, 'contact_id' => $contact->id],
                [
                    'workspace_id' => $channel->workspace_id,
                    'status' => 'bot',
                    'context' => [],
                ],
            );

            if ($conversation->status === 'closed') {
                $conversation->status = 'bot';
                $conversation->closed_at = null;
            }

            $conversation->fill([
                'unread_count' => $conversation->unread_count + 1,
                'last_message_at' => now(),
                'customer_service_window_expires_at' => now()->addHours(24),
            ])->save();

            $content = $this->extractInboundContent($inbound);
            $message = Message::query()->firstOrCreate(
                ['meta_message_id' => $messageId],
                [
                    'workspace_id' => $channel->workspace_id,
                    'conversation_id' => $conversation->id,
                    'direction' => 'inbound',
                    'type' => $content['type'],
                    'status' => 'received',
                    'reply_to_meta_message_id' => data_get($inbound, 'context.id'),
                    'content' => $content,
                ],
            );

            return [$conversation->fresh(['contact', 'channel']), $message->wasRecentlyCreated];
        }, 3);

        if (! $created || $conversation->status === 'human') {
            return;
        }

        Cache::lock("conversation-runtime:{$conversation->id}", 20)->block(5, function () use ($conversation, $inbound): void {
            $this->advance(
                $conversation->fresh(['contact', 'channel']),
                $this->extractSelection($inbound),
                $this->extractInboundText($inbound),
                $this->extractInboundMedia($inbound),
            );
        });
    }

    public function applyStatuses(array $statuses): void
    {
        foreach ($statuses as $status) {
            $metaId = $status['id'] ?? null;
            $state = $status['status'] ?? null;

            if (! $metaId || ! in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) {
                continue;
            }

            $message = Message::query()->where('meta_message_id', $metaId)->first();
            if (! $message) {
                continue;
            }

            $updates = ['status' => $state];
            if (in_array($state, ['sent', 'delivered', 'read'], true)) {
                $updates[$state.'_at'] = now();
            }

            if ($state === 'failed') {
                $updates['error'] = $status['errors'] ?? [['message' => 'Falha não detalhada pela Meta.']];
            }
            $message->update($updates);
        }
    }

    public function sendManualReply(Conversation $conversation, string $text, int $userId): Message
    {
        if ($conversation->status !== 'human') {
            throw new RuntimeException('Ative o atendimento humano antes de responder ao contato.');
        }

        if (! $conversation->customer_service_window_expires_at?->isFuture()) {
            throw new RuntimeException('A janela de atendimento de 24 horas expirou. Use um template aprovado pela Meta.');
        }

        $result = $this->whatsApp->sendText($conversation->channel, $conversation->contact->wa_id, $text);

        return DB::transaction(function () use ($conversation, $text, $userId, $result): Message {
            $message = Message::create([
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'direction' => 'outbound',
                'type' => 'text',
                'status' => $result['success'] ? 'sent' : 'failed',
                'meta_message_id' => $result['message_id'],
                'content' => ['type' => 'text', 'text' => $text],
                'error' => $result['error'],
                'sent_at' => $result['success'] ? now() : null,
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            return $message;
        }, 3);
    }

    private function advance(
        Conversation $conversation,
        ?string $selection,
        ?string $inboundText = null,
        ?array $inboundMedia = null,
    ): void {
        $execution = $conversation->executions()->whereIn('status', ['running', 'waiting'])->latest()->first();

        if (! $execution) {
            $automation = Automation::query()
                ->where('workspace_id', $conversation->workspace_id)
                ->where('status', 'active')
                ->where('trigger_type', 'incoming_message')
                ->with('publishedVersion')
                ->oldest('id')
                ->first();

            if (! $automation?->publishedVersion) {
                return;
            }

            $execution = FlowExecution::create([
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'automation_version_id' => $automation->publishedVersion->id,
                'status' => 'running',
                'context' => [],
                'started_at' => now(),
            ]);

            $conversation->update([
                'automation_version_id' => $automation->publishedVersion->id,
                'current_node_id' => null,
            ]);
        }

        $graph = $execution->version->graph;
        $nodes = collect($graph['nodes'] ?? [])->keyBy('id');
        $edges = collect($graph['edges'] ?? []);
        $current = $execution->current_node_id
            ? $nodes->get($execution->current_node_id)
            : $nodes->firstWhere('type', 'trigger');

        if (! $current) {
            $this->complete($execution, $conversation);

            return;
        }

        if (($current['type'] ?? null) === 'menu' && $execution->status === 'waiting') {
            $selection = $this->resolveSelection($current, $selection);

            if ($selection === null) {
                $this->sendNode($conversation, $current, $this->executionVariables($execution));

                return;
            }

            if (filled($saveTo = data_get($current, 'data.saveTo'))) {
                $this->storeVariable($execution, (string) $saveTo, $selection);
                $label = collect(data_get($current, 'data.options', []))
                    ->firstWhere('id', $selection)['label'] ?? null;
                if ($label !== null) {
                    $this->storeVariable($execution, $saveTo.'_label', (string) $label);
                }
            }

            $current = $this->nextNode($nodes, $edges, $current['id'], $selection);
            $execution->update(['status' => 'running', 'current_node_id' => $current['id'] ?? null]);
        }

        if (($current['type'] ?? null) === 'input' && $execution->status === 'waiting') {
            $answer = $inboundText !== null ? trim($inboundText) : '';
            $data = $current['data'] ?? [];

            // Nó de coleta de anexos: mídia recebida é acumulada na variável
            // e o nó continua aguardando até '0'/token de conclusão ou o limite.
            if (($data['validation'] ?? null) === 'media') {
                $variable = (string) ($data['variable'] ?? 'anexos');
                $max = max(1, (int) ($data['maxItems'] ?? 5));
                $items = $execution->context['variables'][$variable] ?? [];
                $items = is_array($items) ? array_values($items) : [];

                if ($inboundMedia !== null) {
                    $items[] = $inboundMedia;
                    $this->storeVariable($execution, $variable, $items);
                    $count = count($items);

                    if ($count < $max) {
                        $this->sendInputAck(
                            $conversation,
                            $current,
                            $execution,
                            "📎 Anexo recebido ({$count}/{$max}). Envie outro arquivo ou *0* para concluir.",
                        );

                        return;
                    }

                    $this->sendInputAck(
                        $conversation,
                        $current,
                        $execution,
                        "📎 Anexo recebido — limite de {$max} atingido. Seguindo...",
                    );
                } elseif ($answer === '') {
                    $this->sendNode($conversation, $current, $this->executionVariables($execution));

                    return;
                } elseif (! FlowInputValidator::isSkipToken($answer)) {
                    $this->sendInputRetry($conversation, $current, $execution);

                    return;
                }

                $this->storeVariable($execution, $variable.'_total', (string) count($items));
                $current = $this->nextNode($nodes, $edges, $current['id']);
                $execution->update(['status' => 'running', 'current_node_id' => $current['id'] ?? null]);
            } else {
                if ($answer === '') {
                    $this->sendNode($conversation, $current, $this->executionVariables($execution));

                    return;
                }

                if (! (($data['optional'] ?? false) && FlowInputValidator::isSkipToken($answer))
                    && ! FlowInputValidator::validate($data, $answer)) {
                    $this->sendInputRetry($conversation, $current, $execution);

                    return;
                }

                $variable = (string) ($data['variable'] ?? 'resposta');
                $skipped = (bool) ($data['optional'] ?? false) && FlowInputValidator::isSkipToken($answer);
                $this->storeVariable($execution, $variable, $skipped ? '' : $answer);
                $execution->update(['status' => 'running']);

                $current = $this->nextNode($nodes, $edges, $current['id']);
                $execution->update(['current_node_id' => $current['id'] ?? null]);
            }
        }

        if (($current['type'] ?? null) === 'message'
            && filled(data_get($current, 'data.continueLabel'))
            && $execution->status === 'waiting') {
            if (! $this->isContinueSelection($current, $selection)) {
                $this->sendContinuePrompt($conversation, $current);

                return;
            }

            $current = $this->nextNode($nodes, $edges, $current['id']);
            $execution->update(['status' => 'running', 'current_node_id' => $current['id'] ?? null]);
        }

        $steps = 0;
        while ($current && $steps++ < 25) {
            $started = hrtime(true);
            $type = $current['type'] ?? null;
            $output = null;

            if ($type === 'trigger') {
                $next = $this->nextNode($nodes, $edges, $current['id']);
            } elseif ($type === 'message') {
                $output = $this->sendNode($conversation, $current, $this->executionVariables($execution));
                if (filled(data_get($current, 'data.continueLabel'))) {
                    $output['continue'] = $this->sendContinuePrompt($conversation, $current);
                    $execution->update(['status' => 'waiting', 'current_node_id' => $current['id']]);
                    $conversation->update(['current_node_id' => $current['id']]);
                    $this->recordEvent($execution, $current['id'], 'waiting_input', null, $output, $started);

                    return;
                }
                $next = $this->nextNode($nodes, $edges, $current['id']);
            } elseif ($type === 'menu' || $type === 'input') {
                $output = $this->sendNode($conversation, $current, $this->executionVariables($execution));
                $execution->update(['status' => 'waiting', 'current_node_id' => $current['id']]);
                $conversation->update(['current_node_id' => $current['id']]);
                $this->recordEvent($execution, $current['id'], 'waiting_input', null, $output, $started);

                return;
            } elseif ($type === 'action') {
                $output = $this->runAction($current, $conversation, $execution);
                $handle = $output['success'] ? 'success' : 'error';
                $next = $this->nextNode($nodes, $edges, $current['id'], $handle)
                    ?? $this->nextNode($nodes, $edges, $current['id']);
                $this->recordEvent(
                    $execution,
                    $current['id'],
                    $output['success'] ? 'action_executed' : 'action_failed',
                    null,
                    $output,
                    $started,
                );

                if ($next === null && ! $output['success']) {
                    $execution->update([
                        'status' => 'failed',
                        'current_node_id' => $current['id'],
                        'error_message' => (string) ($output['error'] ?? 'Ação falhou.'),
                    ]);

                    return;
                }

                $current = $next;
                $execution->update(['current_node_id' => $current['id'] ?? null]);

                continue;
            } elseif ($type === 'handoff') {
                if (filled(data_get($current, 'data.text'))) {
                    $output = $this->sendNode($conversation, [
                        ...$current,
                        'type' => 'message',
                    ], $this->executionVariables($execution));
                }
                $execution->update(['status' => 'completed', 'current_node_id' => $current['id'], 'completed_at' => now()]);
                $conversation->update(['status' => 'human', 'current_node_id' => $current['id']]);
                $this->recordEvent($execution, $current['id'], 'human_handoff', null, $output, $started);

                return;
            } elseif ($type === 'end') {
                $this->recordEvent($execution, $current['id'], 'completed', null, null, $started);
                $this->complete($execution, $conversation);

                return;
            } else {
                $next = $this->nextNode($nodes, $edges, $current['id']);
            }

            $this->recordEvent($execution, $current['id'], 'node_executed', null, $output, $started);
            $current = $next;
            $execution->update(['current_node_id' => $current['id'] ?? null]);
        }

        if ($steps >= 25) {
            $execution->update(['status' => 'failed', 'error_message' => 'Limite de 25 etapas por avanço excedido.']);
            throw new RuntimeException('O fluxo excedeu o limite de segurança por avanço.');
        }

        $this->complete($execution, $conversation);
    }

    private function sendNode(Conversation $conversation, array $node, array $variables = []): array
    {
        $data = $node['data'] ?? [];
        $results = [];

        if (($node['type'] ?? null) === 'menu') {
            if (filled($data['introText'] ?? null)) {
                $intro = $this->interpolate((string) $data['introText'], $conversation, $variables);
                $introResult = $this->whatsApp->sendText(
                    $conversation->channel,
                    $conversation->contact->wa_id,
                    $intro,
                );
                $this->storeOutboundMessage($conversation, $node, $introResult, 'text', [
                    'type' => 'text',
                    'text' => $intro,
                    'node_id' => $node['id'],
                ]);
                $this->throwWhenSendFailed($introResult);
                $results[] = $introResult['message_id'];
            }

            $text = $this->interpolate((string) ($data['text'] ?? ''), $conversation, $variables);
            $result = $this->whatsApp->sendMenu(
                $conversation->channel,
                $conversation->contact->wa_id,
                $text,
                $data['options'] ?? [],
                $data['menuMode'] ?? 'list',
                [
                    'header' => $data['header'] ?? null,
                    'buttonLabel' => $data['buttonLabel'] ?? null,
                    'sectionTitle' => $data['sectionTitle'] ?? null,
                ],
            );
            $this->storeOutboundMessage($conversation, $node, $result, 'interactive', [
                'type' => 'menu',
                'text' => $text,
                'options' => $data['options'] ?? null,
                'header' => $data['header'] ?? null,
                'button_label' => $data['buttonLabel'] ?? null,
                'section_title' => $data['sectionTitle'] ?? null,
                'node_id' => $node['id'],
            ]);
            $this->throwWhenSendFailed($result);
            $results[] = $result['message_id'];

            return ['meta_message_ids' => array_values(array_filter($results))];
        }

        foreach ($this->messageFormatter->payloads($data) as $payload) {
            $text = $this->interpolate($this->messageFormatter->render($payload), $conversation, $variables);
            $result = $this->whatsApp->sendText(
                $conversation->channel,
                $conversation->contact->wa_id,
                $text,
            );
            $this->storeOutboundMessage($conversation, $node, $result, 'text', [
                'type' => 'text',
                'text' => $text,
                'links' => $payload['links'],
                'node_id' => $node['id'],
            ]);
            $this->throwWhenSendFailed($result);
            $results[] = $result['message_id'];
        }

        return ['meta_message_ids' => array_values(array_filter($results))];
    }

    private function sendContinuePrompt(Conversation $conversation, array $node): array
    {
        $label = (string) data_get($node, 'data.continueLabel', 'Continuar');
        $result = $this->whatsApp->sendMenu(
            $conversation->channel,
            $conversation->contact->wa_id,
            '↩️',
            [['id' => 'continue', 'label' => $label]],
            'buttons',
        );
        $this->storeOutboundMessage($conversation, $node, $result, 'interactive', [
            'type' => 'menu',
            'text' => '↩️',
            'options' => [['id' => 'continue', 'label' => $label]],
            'node_id' => $node['id'],
        ]);
        $this->throwWhenSendFailed($result);

        return ['meta_message_id' => $result['message_id']];
    }

    private function storeOutboundMessage(
        Conversation $conversation,
        array $node,
        array $result,
        string $type,
        array $content,
    ): void {
        Message::create([
            'workspace_id' => $conversation->workspace_id,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => $type,
            'status' => $result['success'] ? 'sent' : 'failed',
            'meta_message_id' => $result['message_id'],
            'content' => $content,
            'error' => $result['error'],
            'sent_at' => $result['success'] ? now() : null,
        ]);
    }

    private function throwWhenSendFailed(array $result): void
    {
        if (! $result['success']) {
            throw new RuntimeException((string) data_get($result, 'error.message', 'Falha ao enviar mensagem pela Meta.'));
        }
    }

    private function isContinueSelection(array $node, ?string $selection): bool
    {
        if ($selection === null || $selection === '') {
            return false;
        }

        return $selection === 'continue'
            || mb_strtolower(trim($selection)) === mb_strtolower(trim((string) data_get($node, 'data.continueLabel')));
    }

    private function sendInputRetry(Conversation $conversation, array $node, FlowExecution $execution): void
    {
        $text = trim((string) data_get($node, 'data.retryText'));
        if ($text === '') {
            $text = (string) data_get($node, 'data.text');
        }

        $text = $this->interpolate($text, $conversation, $this->executionVariables($execution));
        $result = $this->whatsApp->sendText(
            $conversation->channel,
            $conversation->contact->wa_id,
            $text,
        );
        $this->storeOutboundMessage($conversation, $node, $result, 'text', [
            'type' => 'text',
            'text' => $text,
            'node_id' => $node['id'],
        ]);
        $this->throwWhenSendFailed($result);
    }

    private function runAction(array $node, Conversation $conversation, FlowExecution $execution): array
    {
        $actionId = (string) data_get($node, 'data.action', '');
        $action = $actionId !== '' ? $this->actions->find($actionId) : null;

        if (! $action) {
            return ['success' => false, 'error' => "Ação '{$actionId}' não está registrada."];
        }

        try {
            $result = $action->execute($node['data'] ?? [], $conversation, $execution);
        } catch (Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }

        if (! empty($result['variables']) && is_array($result['variables'])) {
            $context = $execution->context ?? [];
            $context['variables'] = array_merge($context['variables'] ?? [], $result['variables']);
            $execution->update(['context' => $context]);
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'summary' => $result['summary'] ?? null,
            'error' => $result['error'] ?? null,
        ];
    }

    private function storeVariable(FlowExecution $execution, string $key, mixed $value): void
    {
        $context = $execution->context ?? [];
        $context['variables'][$key] = $value;
        $execution->update(['context' => $context]);
    }

    private function executionVariables(FlowExecution $execution): array
    {
        return is_array($execution->context['variables'] ?? null)
            ? $execution->context['variables']
            : [];
    }

    /**
     * Descritor de mídia inbound (image/document/audio/video) capturado do
     * webhook da Meta. O binário NÃO é baixado aqui — o download pela Graph
     * API acontece só quando o fluxo precisa encaminhar o anexo ao GED.
     *
     * @return array{media_id: string, type: string, filename: string, mime: ?string, caption: ?string}|null
     */
    private function extractInboundMedia(array $inbound): ?array
    {
        $type = (string) ($inbound['type'] ?? '');
        if (! in_array($type, ['image', 'document', 'audio', 'video'], true)) {
            return null;
        }

        $payload = $inbound[$type] ?? [];
        $mediaId = (string) ($payload['id'] ?? '');
        if ($mediaId === '') {
            return null;
        }

        $filename = (string) ($payload['filename'] ?? '');
        if ($filename === '') {
            $extension = str_contains((string) ($payload['mime_type'] ?? ''), '/')
                ? explode('/', (string) $payload['mime_type'])[1]
                : 'bin';
            $filename = $type.'.'.preg_replace('/[^a-z0-9]/i', '', $extension);
        }

        return [
            'media_id' => $mediaId,
            'type' => $type,
            'filename' => $filename,
            'mime' => $payload['mime_type'] ?? null,
            'caption' => $payload['caption'] ?? null,
        ];
    }

    private function sendInputAck(Conversation $conversation, array $node, FlowExecution $execution, string $text): void
    {
        $text = $this->interpolate($text, $conversation, $this->executionVariables($execution));
        $result = $this->whatsApp->sendText(
            $conversation->channel,
            $conversation->contact->wa_id,
            $text,
        );
        $this->storeOutboundMessage($conversation, $node, $result, 'text', [
            'type' => 'text',
            'text' => $text,
            'node_id' => $node['id'],
        ]);
        $this->throwWhenSendFailed($result);
    }

    private function extractInboundText(array $inbound): ?string
    {
        return ($inbound['type'] ?? null) === 'text'
            ? trim((string) data_get($inbound, 'text.body'))
            : null;
    }

    private function extractInboundContent(array $inbound): array
    {
        $type = $inbound['type'] ?? 'unknown';

        return match ($type) {
            'text' => ['type' => 'text', 'text' => data_get($inbound, 'text.body', '')],
            'interactive' => [
                'type' => 'interactive',
                'selection_id' => data_get($inbound, 'interactive.button_reply.id')
                    ?? data_get($inbound, 'interactive.list_reply.id'),
                'text' => data_get($inbound, 'interactive.button_reply.title')
                    ?? data_get($inbound, 'interactive.list_reply.title', ''),
            ],
            default => ['type' => $type, 'payload' => $inbound[$type] ?? []],
        };
    }

    private function extractSelection(array $inbound): ?string
    {
        if (($inbound['type'] ?? null) === 'interactive') {
            return data_get($inbound, 'interactive.button_reply.id')
                ?? data_get($inbound, 'interactive.list_reply.id');
        }

        return ($inbound['type'] ?? null) === 'text'
            ? trim((string) data_get($inbound, 'text.body'))
            : null;
    }

    private function resolveSelection(array $menu, ?string $selection): ?string
    {
        if ($selection === null || $selection === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($selection));
        foreach (data_get($menu, 'data.options', []) as $option) {
            if (($option['id'] ?? null) === $selection
                || mb_strtolower(trim((string) ($option['label'] ?? ''))) === $normalized) {
                return $option['id'];
            }
        }

        return null;
    }

    private function nextNode($nodes, $edges, string $source, ?string $optionKey = null): ?array
    {
        $candidates = $edges->where('source', $source);
        $edge = $optionKey === null
            ? ($candidates->first(fn (array $edge) => empty($edge['sourceHandle']) && empty(data_get($edge, 'data.optionKey')))
                ?? $candidates->first())
            : $candidates->first(fn (array $edge) => ($edge['sourceHandle'] ?? null) === $optionKey
                || data_get($edge, 'data.optionKey') === $optionKey);

        return $edge ? $nodes->get($edge['target']) : null;
    }

    private function interpolate(string $text, Conversation $conversation, array $variables = []): string
    {
        $replacements = [
            '{{contact.name}}' => $conversation->contact->name ?: 'cliente',
            '{{contact.phone}}' => $conversation->contact->phone_number,
        ];

        foreach ($variables as $key => $value) {
            if (is_scalar($value)) {
                $replacements['{{flow.'.$key.'}}'] = (string) $value;
            }
        }

        return strtr($text, $replacements);
    }

    private function recordEvent(
        FlowExecution $execution,
        ?string $nodeId,
        string $eventType,
        ?array $input,
        ?array $output,
        int $started,
    ): void {
        $execution->events()->create([
            'node_id' => $nodeId,
            'event_type' => $eventType,
            'input' => $input,
            'output' => $output,
            'elapsed_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'created_at' => now(),
        ]);
    }

    private function complete(FlowExecution $execution, Conversation $conversation): void
    {
        $execution->update(['status' => 'completed', 'current_node_id' => null, 'completed_at' => now()]);
        $conversation->update(['current_node_id' => null]);
    }
}
