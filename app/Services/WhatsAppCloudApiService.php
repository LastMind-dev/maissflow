<?php

namespace App\Services;

use App\Models\WhatsAppChannel;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudApiService
{
    public function sendText(WhatsAppChannel $channel, string $to, string $text): array
    {
        return $this->send($channel, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $text],
        ]);
    }

    public function sendMenu(
        WhatsAppChannel $channel,
        string $to,
        string $text,
        array $options,
        string $mode = 'list',
        array $presentation = [],
    ): array {
        if ($mode === 'buttons' && count($options) <= 3) {
            $interactive = [
                'type' => 'button',
                'body' => ['text' => $text],
                'action' => [
                    'buttons' => collect($options)->map(fn (array $option) => [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $option['id'],
                            'title' => mb_substr($option['label'], 0, 20),
                        ],
                    ])->values()->all(),
                ],
            ];
        } else {
            $interactive = [
                'type' => 'list',
                'body' => ['text' => $text],
                'action' => [
                    'button' => mb_substr(
                        (string) ($presentation['buttonLabel'] ?? 'Selecione uma opção'),
                        0,
                        20,
                    ),
                    'sections' => [[
                        'title' => mb_substr(
                            (string) ($presentation['sectionTitle'] ?? 'Menu'),
                            0,
                            24,
                        ),
                        'rows' => collect($options)->map(fn (array $option) => [
                            'id' => $option['id'],
                            'title' => mb_substr($option['label'], 0, 24),
                            'description' => mb_substr((string) ($option['description'] ?? ''), 0, 72),
                        ])->values()->all(),
                    ]],
                ],
            ];
        }

        if (filled($presentation['header'] ?? null)) {
            $interactive['header'] = [
                'type' => 'text',
                'text' => mb_substr((string) $presentation['header'], 0, 60),
            ];
        }

        return $this->send($channel, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    public function testConnection(WhatsAppChannel $channel): array
    {
        $this->assertConfigured($channel);

        $response = Http::acceptJson()
            ->withToken($channel->access_token)
            ->timeout(10)
            ->get($this->baseUrl($channel).'/'.$channel->phone_number_id, [
                'fields' => 'id,display_phone_number,verified_name,quality_rating',
            ]);

        return $this->normalize($response);
    }

    public function registerPhoneNumber(WhatsAppChannel $channel, string $pin): array
    {
        $this->assertConfigured($channel);

        $response = Http::acceptJson()
            ->withToken($channel->access_token)
            ->timeout(15)
            ->post($this->baseUrl($channel).'/'.$channel->phone_number_id.'/register', [
                'messaging_product' => 'whatsapp',
                'pin' => $pin,
            ]);

        return [
            'success' => $response->successful() && $response->json('success') === true,
            'status' => $response->status(),
            'code' => $response->json('error.code'),
            'subcode' => $response->json('error.error_subcode'),
        ];
    }

    private function send(WhatsAppChannel $channel, array $payload): array
    {
        $this->assertConfigured($channel);

        $response = Http::acceptJson()
            ->withToken($channel->access_token)
            ->timeout(15)
            ->retry(2, 250, throw: false)
            ->post($this->baseUrl($channel).'/'.$channel->phone_number_id.'/messages', $payload);

        return $this->normalize($response);
    }

    private function normalize(Response $response): array
    {
        $data = $response->json() ?? [];

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'message_id' => data_get($data, 'messages.0.id'),
            'data' => $data,
            'error' => $response->successful() ? null : data_get($data, 'error', ['message' => $response->body()]),
        ];
    }

    private function baseUrl(WhatsAppChannel $channel): string
    {
        return 'https://graph.facebook.com/'.ltrim($channel->graph_version, '/');
    }

    private function assertConfigured(WhatsAppChannel $channel): void
    {
        if (! filled($channel->phone_number_id) || ! filled($channel->access_token)) {
            throw new RuntimeException('O canal do WhatsApp ainda não possui Phone Number ID e token configurados.');
        }
    }
}
