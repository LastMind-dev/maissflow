<?php

namespace App\Services;

class FlowMessageFormatter
{
    public function payloads(array $data): array
    {
        $messages = is_array($data['messages'] ?? null) ? $data['messages'] : [];

        if ($messages === []) {
            $messages = [[
                'text' => (string) ($data['text'] ?? ''),
                'links' => is_array($data['links'] ?? null) ? $data['links'] : [],
            ]];
        }

        return array_values(array_map(static fn (array $message): array => [
            'text' => (string) ($message['text'] ?? ''),
            'links' => is_array($message['links'] ?? null) ? array_values($message['links']) : [],
        ], $messages));
    }

    public function render(array $payload): string
    {
        $parts = [];
        $text = (string) ($payload['text'] ?? '');

        if ($text !== '') {
            $parts[] = rtrim($text);
        }

        foreach ($payload['links'] ?? [] as $link) {
            $url = trim((string) ($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $label = trim((string) ($link['label'] ?? ''));
            $parts[] = $label === '' ? $url : "{$label}: {$url}";
        }

        return implode("\n", $parts);
    }
}
