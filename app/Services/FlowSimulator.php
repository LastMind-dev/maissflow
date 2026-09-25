<?php

namespace App\Services;

use App\Services\Actions\ActionRegistry;
use RuntimeException;

class FlowSimulator
{
    public function __construct(
        private readonly FlowMessageFormatter $messageFormatter,
        private readonly ActionRegistry $actions = new ActionRegistry,
    ) {}

    public function run(array $graph, array $inputs = []): array
    {
        $nodes = collect($graph['nodes'] ?? [])->keyBy('id');
        $edges = collect($graph['edges'] ?? []);
        $current = $nodes->firstWhere('type', 'trigger');
        $transcript = [];
        $visitedSteps = 0;
        $inputIndex = 0;
        $variables = [];

        while ($current && $visitedSteps++ < 50) {
            $type = $current['type'] ?? null;
            $data = $current['data'] ?? [];

            if ($type === 'trigger') {
                $current = $this->nextNode($nodes, $edges, $current['id']);

                continue;
            }

            if ($type === 'message') {
                foreach ($this->messageFormatter->payloads($data) as $payload) {
                    $transcript[] = [
                        'direction' => 'outbound',
                        'type' => 'text',
                        'text' => $this->interpolate($payload['text'], $variables),
                        'links' => $payload['links'],
                        'node_id' => $current['id'],
                    ];
                }

                if (filled($data['continueLabel'] ?? null)) {
                    $options = [['id' => 'continue', 'label' => $data['continueLabel']]];
                    $transcript[] = [
                        'direction' => 'outbound',
                        'type' => 'menu',
                        'text' => '↩️',
                        'options' => $options,
                        'node_id' => $current['id'],
                    ];

                    if (! array_key_exists($inputIndex, $inputs)) {
                        return [
                            'status' => 'waiting_input',
                            'current_node_id' => $current['id'],
                            'transcript' => $transcript,
                        ];
                    }

                    $selected = (string) $inputs[$inputIndex++];
                    if ($selected !== 'continue'
                        && mb_strtolower(trim($selected)) !== mb_strtolower(trim((string) $data['continueLabel']))) {
                        throw new RuntimeException("A opção {$selected} não existe no bloco {$current['id']}.");
                    }

                    $transcript[] = [
                        'direction' => 'inbound',
                        'type' => 'selection',
                        'text' => $data['continueLabel'],
                        'option_id' => 'continue',
                    ];
                }

                $current = $this->nextNode($nodes, $edges, $current['id']);

                continue;
            }

            if ($type === 'menu') {
                $options = $data['options'] ?? [];
                if (filled($data['introText'] ?? null)) {
                    $transcript[] = [
                        'direction' => 'outbound',
                        'type' => 'text',
                        'text' => $this->interpolate($data['introText'], $variables),
                        'links' => [],
                        'node_id' => $current['id'],
                    ];
                }
                $transcript[] = [
                    'direction' => 'outbound',
                    'type' => 'menu',
                    'text' => $this->interpolate($data['text'] ?? '', $variables),
                    'options' => $options,
                    'header' => $data['header'] ?? null,
                    'button_label' => $data['buttonLabel'] ?? null,
                    'section_title' => $data['sectionTitle'] ?? null,
                    'node_id' => $current['id'],
                ];

                if (! array_key_exists($inputIndex, $inputs)) {
                    return [
                        'status' => 'waiting_input',
                        'current_node_id' => $current['id'],
                        'transcript' => $transcript,
                    ];
                }

                $selected = (string) $inputs[$inputIndex++];
                $option = collect($options)->first(fn (array $item) => ($item['id'] ?? null) === $selected);
                if (! $option) {
                    throw new RuntimeException("A opção {$selected} não existe no menu {$current['id']}.");
                }

                $transcript[] = [
                    'direction' => 'inbound',
                    'type' => 'selection',
                    'text' => $option['label'],
                    'option_id' => $selected,
                ];
                if (filled($data['saveTo'] ?? null)) {
                    $variables[(string) $data['saveTo']] = (string) $option['id'];
                    $variables[$data['saveTo'].'_label'] = (string) $option['label'];
                }
                $current = $this->nextNode($nodes, $edges, $current['id'], $selected);

                continue;
            }

            if ($type === 'input') {
                $transcript[] = [
                    'direction' => 'outbound',
                    'type' => 'input',
                    'text' => $this->interpolate((string) ($data['text'] ?? ''), $variables),
                    'node_id' => $current['id'],
                ];

                if (! array_key_exists($inputIndex, $inputs)) {
                    return [
                        'status' => 'waiting_input',
                        'current_node_id' => $current['id'],
                        'transcript' => $transcript,
                    ];
                }

                $answer = trim((string) $inputs[$inputIndex++]);
                $optional = (bool) ($data['optional'] ?? false);
                if ($optional && FlowInputValidator::isSkipToken($answer)) {
                    $answer = '';
                } elseif (! FlowInputValidator::validate($data, $answer)) {
                    throw new RuntimeException("Resposta inválida para a pergunta {$current['id']}.");
                }

                $variables[(string) ($data['variable'] ?? 'resposta')] = $answer;
                $transcript[] = [
                    'direction' => 'inbound',
                    'type' => 'text',
                    'text' => $answer === '' ? '(pulado)' : $answer,
                    'node_id' => $current['id'],
                ];
                $current = $this->nextNode($nodes, $edges, $current['id']);

                continue;
            }

            if ($type === 'action') {
                $actionId = (string) ($data['action'] ?? '');
                $action = $actionId !== '' ? $this->actions->find($actionId) : null;
                $transcript[] = [
                    'direction' => 'system',
                    'type' => 'action',
                    'text' => $action
                        ? "Ação simulada: {$action->label()}"
                        : "Ação não registrada: {$actionId}",
                    'node_id' => $current['id'],
                ];

                if ($action) {
                    $variables = array_merge($variables, $action->simulatedVariables());
                    $current = $this->nextNode($nodes, $edges, $current['id'], 'success')
                        ?? $this->nextNode($nodes, $edges, $current['id']);
                } else {
                    $current = $this->nextNode($nodes, $edges, $current['id'], 'error')
                        ?? $this->nextNode($nodes, $edges, $current['id']);
                }

                continue;
            }

            if ($type === 'handoff') {
                $transcript[] = [
                    'direction' => 'system',
                    'type' => 'handoff',
                    'text' => $this->interpolate(
                        $data['text'] ?? 'Conversa transferida para a equipe.',
                        $variables,
                    ),
                    'node_id' => $current['id'],
                ];

                return ['status' => 'human_handoff', 'current_node_id' => $current['id'], 'transcript' => $transcript];
            }

            if ($type === 'end') {
                return ['status' => 'completed', 'current_node_id' => $current['id'], 'transcript' => $transcript];
            }

            $transcript[] = [
                'direction' => 'system',
                'type' => $type,
                'text' => $data['label'] ?? ucfirst((string) $type),
                'node_id' => $current['id'],
            ];
            $current = $this->nextNode($nodes, $edges, $current['id']);
        }

        return [
            'status' => $visitedSteps >= 50 ? 'step_limit' : 'completed',
            'current_node_id' => $current['id'] ?? null,
            'transcript' => $transcript,
        ];
    }

    private function nextNode($nodes, $edges, string $source, ?string $optionKey = null): ?array
    {
        $candidates = $edges->where('source', $source);

        if ($optionKey !== null) {
            $edge = $candidates->first(function (array $edge) use ($optionKey): bool {
                return ($edge['sourceHandle'] ?? null) === $optionKey
                    || data_get($edge, 'data.optionKey') === $optionKey;
            });
        } else {
            $edge = $candidates->first(function (array $edge): bool {
                return empty($edge['sourceHandle']) && empty(data_get($edge, 'data.optionKey'));
            }) ?? $candidates->first();
        }

        return $edge ? $nodes->get($edge['target']) : null;
    }

    private function interpolate(string $text, array $variables): string
    {
        $replacements = [
            '{{contact.name}}' => 'Cliente Simulado',
            '{{contact.phone}}' => '5517999999000',
        ];

        foreach ($variables as $key => $value) {
            if (is_scalar($value)) {
                $replacements['{{flow.'.$key.'}}'] = (string) $value;
            }
        }

        return strtr($text, $replacements);
    }
}
