<?php

namespace App\Services;

use App\Services\Actions\ActionRegistry;

class FlowGraphValidator
{
    private const ALLOWED_TYPES = [
        'trigger', 'message', 'menu', 'input', 'condition', 'action', 'delay', 'handoff', 'end',
    ];

    public function __construct(private readonly ActionRegistry $actions = new ActionRegistry) {}

    public function validate(array $graph): array
    {
        $errors = [];
        $warnings = [];
        $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
        $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];

        if ($nodes === []) {
            $errors[] = 'O fluxo precisa ter ao menos um nó.';
        }

        if (count($nodes) > 200) {
            $errors[] = 'O limite de segurança é de 200 nós por automação.';
        }

        if (count($edges) > 500) {
            $errors[] = 'O limite de segurança é de 500 conexões por automação.';
        }

        $nodeMap = [];
        $triggerIds = [];

        foreach ($nodes as $index => $node) {
            $id = $node['id'] ?? null;
            $type = $node['type'] ?? null;

            if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{1,80}$/', $id)) {
                $errors[] = "Nó na posição {$index} possui identificador inválido.";

                continue;
            }

            if (isset($nodeMap[$id])) {
                $errors[] = "O identificador de nó {$id} está duplicado.";

                continue;
            }

            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                $errors[] = "O nó {$id} possui tipo não suportado.";
            }

            $nodeMap[$id] = $node;

            if ($type === 'trigger') {
                $triggerIds[] = $id;
            }

            $this->validateNodeContent($node, $errors, $warnings);
        }

        if (count($triggerIds) !== 1) {
            $errors[] = 'O fluxo publicado precisa ter exatamente um gatilho de entrada.';
        }

        $edgeIds = [];
        $adjacency = [];
        foreach ($edges as $index => $edge) {
            $id = $edge['id'] ?? "posição {$index}";
            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;

            if (isset($edgeIds[$id])) {
                $errors[] = "A conexão {$id} está duplicada.";
            }
            $edgeIds[$id] = true;

            if (! isset($nodeMap[$source])) {
                $errors[] = "A conexão {$id} aponta para uma origem inexistente.";
            }

            if (! isset($nodeMap[$target])) {
                $errors[] = "A conexão {$id} aponta para um destino inexistente.";
            }

            if ($source === $target) {
                $errors[] = "A conexão {$id} não pode retornar ao próprio nó.";
            }

            if (isset($nodeMap[$source], $nodeMap[$target])) {
                $adjacency[$source][] = $target;
            }
        }

        if (count($triggerIds) === 1) {
            $reachable = $this->reachableFrom($triggerIds[0], $adjacency);
            foreach (array_keys($nodeMap) as $nodeId) {
                if (! isset($reachable[$nodeId])) {
                    $warnings[] = "O nó {$nodeId} não é alcançável a partir do gatilho.";
                }
            }
        }

        foreach ($nodeMap as $nodeId => $node) {
            if (! in_array($node['type'] ?? null, ['end', 'handoff'], true)
                && empty($adjacency[$nodeId])) {
                $warnings[] = "O nó {$nodeId} não possui uma próxima etapa.";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'stats' => ['nodes' => count($nodes), 'edges' => count($edges)],
        ];
    }

    private function validateNodeContent(array $node, array &$errors, array &$warnings): void
    {
        $id = $node['id'] ?? 'desconhecido';
        $type = $node['type'] ?? null;
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        if (in_array($type, ['message', 'menu'], true)) {
            $text = trim((string) ($data['text'] ?? ''));
            if ($text === '') {
                $errors[] = "O nó {$id} precisa ter uma mensagem.";
            } elseif (mb_strlen($text) > 4096) {
                $errors[] = "A mensagem do nó {$id} excede 4.096 caracteres.";
            }
        }

        if ($type === 'message') {
            $messages = is_array($data['messages'] ?? null) ? $data['messages'] : [];
            if (count($messages) > 20) {
                $errors[] = "O nó {$id} excede o limite de 20 mensagens no mesmo bloco.";
            }

            foreach ($messages as $messageIndex => $message) {
                $messageText = trim((string) ($message['text'] ?? ''));
                if ($messageText === '' || mb_strlen($messageText) > 4096) {
                    $errors[] = 'A mensagem '.($messageIndex + 1)." do nó {$id} deve ter entre 1 e 4.096 caracteres.";
                }
                $this->validateLinks($id, $message['links'] ?? [], $errors);
            }

            $this->validateLinks($id, $data['links'] ?? [], $errors);

            if (filled($data['continueLabel'] ?? null)
                && mb_strlen(trim((string) $data['continueLabel'])) > 20) {
                $errors[] = "O botão de continuação do nó {$id} excede 20 caracteres.";
            }
        }

        if ($type === 'menu') {
            $options = is_array($data['options'] ?? null) ? $data['options'] : [];
            $mode = $data['menuMode'] ?? 'list';
            $limit = $mode === 'buttons' ? 3 : 10;

            if ($options === []) {
                $errors[] = "O menu {$id} precisa ter ao menos uma opção.";
            }

            if (count($options) > $limit) {
                $errors[] = "O menu {$id} excede o limite de {$limit} opções do formato escolhido.";
            }

            $optionIds = [];
            foreach ($options as $option) {
                $optionId = $option['id'] ?? null;
                $label = trim((string) ($option['label'] ?? ''));

                if (! is_string($optionId) || ! preg_match('/^[A-Za-z0-9_-]{1,80}$/', $optionId)) {
                    $errors[] = "O menu {$id} contém uma opção com identificador inválido.";
                } elseif (isset($optionIds[$optionId])) {
                    $errors[] = "O menu {$id} contém a opção {$optionId} duplicada.";
                }
                $optionIds[$optionId] = true;

                if ($label === '' || mb_strlen($label) > 24) {
                    $errors[] = "Cada opção do menu {$id} deve ter entre 1 e 24 caracteres.";
                }

                if (mb_strlen((string) ($option['description'] ?? '')) > 72) {
                    $errors[] = "A descrição de uma opção do menu {$id} excede 72 caracteres.";
                }
            }

            foreach ([
                'header' => 60,
                'buttonLabel' => 20,
                'sectionTitle' => 24,
            ] as $field => $limit) {
                if (filled($data[$field] ?? null)
                    && mb_strlen(trim((string) $data[$field])) > $limit) {
                    $errors[] = "O campo {$field} do menu {$id} excede {$limit} caracteres.";
                }
            }

            if (mb_strlen((string) ($data['introText'] ?? '')) > 4096) {
                $errors[] = "O texto introdutório do menu {$id} excede 4.096 caracteres.";
            }

            if (isset($data['saveTo'])
                && ! preg_match('/^[a-z][a-z0-9_]{0,39}$/', (string) $data['saveTo'])) {
                $errors[] = "A variável de destino do menu {$id} é inválida.";
            }
        }

        if ($type === 'input') {
            $prompt = trim((string) ($data['text'] ?? ''));
            if ($prompt === '' || mb_strlen($prompt) > 4096) {
                $errors[] = "O nó de pergunta {$id} precisa ter um texto entre 1 e 4.096 caracteres.";
            }

            $variable = (string) ($data['variable'] ?? '');
            if (! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $variable)) {
                $errors[] = "O nó {$id} precisa de um nome de variável válido (ex.: nome, cpf).";
            }

            $rule = (string) ($data['validation'] ?? 'any');
            if (! in_array($rule, FlowInputValidator::RULES, true)) {
                $errors[] = "O nó {$id} usa uma validação desconhecida ({$rule}).";
            }

            if (mb_strlen((string) ($data['retryText'] ?? '')) > 4096) {
                $errors[] = "O texto de nova tentativa do nó {$id} excede 4.096 caracteres.";
            }

            $maxLength = (int) ($data['maxLength'] ?? 5000);
            if ($maxLength < 1 || $maxLength > 5000) {
                $errors[] = "O tamanho máximo da resposta do nó {$id} deve ficar entre 1 e 5.000.";
            }
        }

        if ($type === 'action') {
            $actionId = (string) ($data['action'] ?? '');
            if ($actionId === '') {
                $errors[] = "O nó de ação {$id} precisa informar qual ação executar.";
            } elseif (! in_array($actionId, $this->actions->ids(), true)) {
                $errors[] = "A ação {$actionId} do nó {$id} não está na lista permitida.";
            }
        }

        if ($type === 'delay' && ((int) ($data['seconds'] ?? 0) < 1)) {
            $errors[] = "O atraso do nó {$id} precisa ser maior que zero.";
        }

        if ($type === 'condition' && empty($data['field'])) {
            $warnings[] = "A condição {$id} ainda não informa o campo a avaliar.";
        }
    }

    private function validateLinks(string $nodeId, mixed $links, array &$errors): void
    {
        if (! is_array($links)) {
            $errors[] = "Os links do nó {$nodeId} possuem formato inválido.";

            return;
        }

        foreach ($links as $link) {
            $url = is_array($link) ? trim((string) ($link['url'] ?? '')) : '';
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['https', 'http'], true)) {
                $errors[] = "O nó {$nodeId} contém um link inválido.";
            }

            if (is_array($link) && mb_strlen((string) ($link['label'] ?? '')) > 80) {
                $errors[] = "Um rótulo de link do nó {$nodeId} excede 80 caracteres.";
            }
        }
    }

    private function reachableFrom(string $start, array $adjacency): array
    {
        $visited = [];
        $queue = [$start];

        while ($queue !== []) {
            $nodeId = array_shift($queue);
            if (isset($visited[$nodeId])) {
                continue;
            }
            $visited[$nodeId] = true;
            foreach ($adjacency[$nodeId] ?? [] as $target) {
                $queue[] = $target;
            }
        }

        return $visited;
    }
}
