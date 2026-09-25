<?php

namespace App\Services\Actions;

use App\Models\Conversation;
use App\Models\FlowExecution;
use App\Services\GedPortalClient;
use App\Services\WhatsAppCloudApiService;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;

class OuvidoriaSubmitAction implements FlowAction
{
    private const REQUIRED_VARIABLES = ['tipo', 'assunto', 'descricao'];

    private const REQUIRED_ESIC = ['nome', 'cpf', 'email', 'assunto', 'descricao'];

    private const MAX_ANEXOS = 5;

    public function id(): string
    {
        return 'ouvidoria.submit';
    }

    public function label(): string
    {
        return 'Registrar na Ouvidoria (MAISSDoc)';
    }

    public function description(): string
    {
        return 'Envia a manifestação coletada no fluxo para a ouvidoria/e-SIC do GED e guarda o protocolo em {{flow.ouvidoria_protocolo}}.';
    }

    public function simulatedVariables(): array
    {
        return [
            'ouvidoria_protocolo' => '2026000000',
            'ouvidoria_codigo' => 'SIM000',
        ];
    }

    public function execute(array $config, Conversation $conversation, FlowExecution $execution): array
    {
        $variables = $execution->context['variables'] ?? [];

        if (filled($variables['ouvidoria_protocolo'] ?? null) || filled($variables['ouvidoria_enviada'] ?? null)) {
            return [
                'success' => true,
                'summary' => 'Manifestação já registrada nesta execução.',
            ];
        }

        $canal = ($config['canal'] ?? 'ouvidoria') === 'esic' ? 'esic' : 'ouvidoria';

        $missing = array_values(array_filter(
            $canal === 'esic' ? self::REQUIRED_ESIC : self::REQUIRED_VARIABLES,
            fn (string $key): bool => blank($variables[$key] ?? null),
        ));
        if ($missing !== []) {
            return ['success' => false, 'error' => 'Variáveis obrigatórias ausentes: '.implode(', ', $missing)];
        }

        $client = app(GedPortalClient::class);
        if ($client->disponivel()) {
            return $this->submitViaApi($client, $canal, $variables, $conversation, $execution);
        }

        // Fallback legado: formulário público com CSRF + sessão (ouvidoria apenas).
        if ($canal === 'esic') {
            return ['success' => false, 'error' => 'O canal e-SIC exige a API do GED (GED_API_URL/GED_API_TOKEN).'];
        }

        return $this->submitViaFormulario($variables, $conversation);
    }

    /**
     * Caminho oficial: POST JSON em /api/portal/v1/manifestacoes com Bearer
     * token e Idempotency-Key derivado da execução (retry seguro). Anexos
     * coletados no fluxo são enviados depois, um a um, autenticados pelo
     * código do cidadão.
     */
    private function submitViaApi(
        GedPortalClient $client,
        string $canal,
        array $variables,
        Conversation $conversation,
        FlowExecution $execution,
    ): array {
        $anonymous = $canal === 'ouvidoria' && ($variables['identificacao'] ?? null) === 'anonimo';

        $payload = array_filter([
            'canal' => $canal,
            'anonimo' => $anonymous ?: null,
            'nome_solicitante' => $anonymous ? null : ($variables['nome'] ?? null),
            'cpf' => $anonymous ? null : $this->formatCpf($variables['cpf'] ?? null),
            'email' => $anonymous ? null : ($variables['email'] ?? null),
            'telefone' => $anonymous ? null : $this->formatPhone(
                $variables['telefone'] ?? $conversation->contact->wa_id ?? ''
            ),
            'tipo' => $variables['tipo'] ?? null,
            'categoria' => $variables['categoria'] ?? null,
            'assunto' => $variables['assunto'],
            'descricao' => $variables['descricao'],
            'endereco' => $variables['endereco'] ?? null,
            'bairro' => $variables['bairro'] ?? null,
            'referencia' => $variables['referencia'] ?? null,
        ], fn ($value): bool => $value !== null);

        $result = $client->registrar($payload, "maissflow-exec-{$execution->id}-{$canal}");

        if (! $result['success']) {
            $detail = collect((array) data_get($result, 'data.errors', []))->flatten()->first();
            $error = $detail ?: $result['error'] ?: 'A API do GED recusou o envio.';

            return ['success' => false, 'error' => "O GED recusou os dados: {$error}"];
        }

        $protocolo = data_get($result, 'data.protocolo');
        $codigo = data_get($result, 'data.codigo');

        $enviados = $this->enviarAnexos($client, $protocolo, $codigo, $variables, $conversation);

        return [
            'success' => true,
            'variables' => array_filter([
                'ouvidoria_protocolo' => $protocolo,
                'ouvidoria_codigo' => $codigo,
                'ouvidoria_prazo' => data_get($result, 'data.data_limite'),
                'ouvidoria_anexos' => $enviados > 0 ? (string) $enviados : null,
                'ouvidoria_enviada' => '1',
            ]),
            'summary' => 'Manifestação registrada pela API'.($protocolo ? " — protocolo {$protocolo}" : '')
                .($enviados > 0 ? " com {$enviados} anexo(s)" : ''),
        ];
    }

    /**
     * Baixa as mídias coletadas pelo nó de anexos (variável 'anexos') da Graph
     * API e reenvia cada uma ao GED. Falhas individuais são toleradas — a
     * manifestação já existe e o resumo registra quantas subiram.
     */
    private function enviarAnexos(
        GedPortalClient $client,
        ?string $protocolo,
        ?string $codigo,
        array $variables,
        Conversation $conversation,
    ): int {
        $anexos = $variables['anexos'] ?? [];
        if (! is_array($anexos) || $anexos === [] || blank($protocolo) || blank($codigo)) {
            return 0;
        }

        $whatsApp = app(WhatsAppCloudApiService::class);
        $enviados = 0;

        foreach (array_slice($anexos, 0, self::MAX_ANEXOS) as $anexo) {
            $mediaId = (string) ($anexo['media_id'] ?? '');
            if ($mediaId === '') {
                continue;
            }

            $media = $whatsApp->downloadMedia($conversation->channel, $mediaId);
            if ($media === null || ($media['binary'] ?? '') === '') {
                continue;
            }

            $filename = (string) ($anexo['filename'] ?? 'anexo');
            $result = $client->enviarAnexo($protocolo, $codigo, $filename, $media['binary']);
            if ($result['success']) {
                $enviados++;
            }
        }

        return $enviados;
    }

    /** Caminho legado: scraping do formulário público (sem token de API). */
    private function submitViaFormulario(array $variables, Conversation $conversation): array
    {
        $url = (string) config('services.ged.ouvidoria_url', '');
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'GED_OUVIDORIA_URL não configurada.'];
        }

        $timeout = (int) config('services.ged.timeout', 20);
        $cookies = new CookieJar;

        $form = Http::withOptions(['cookies' => $cookies])
            ->timeout($timeout)
            ->retry(2, 300, throw: false)
            ->get($url);

        if (! $form->successful()) {
            return ['success' => false, 'error' => "Não foi possível abrir o formulário da ouvidoria (HTTP {$form->status()})."];
        }

        if (! preg_match('/name="_token"\s+value="([^"]+)"/', $form->body(), $tokenMatch)) {
            return ['success' => false, 'error' => 'Token de segurança do formulário não encontrado.'];
        }

        $anonymous = ($variables['identificacao'] ?? null) === 'anonimo';
        $fields = array_filter([
            '_token' => $tokenMatch[1],
            'website' => '',
            'robots' => '',
            'anonimo' => $anonymous ? '1' : null,
            'nome_solicitante' => $anonymous ? null : ($variables['nome'] ?? null),
            'cpf' => $anonymous ? null : $this->formatCpf($variables['cpf'] ?? null),
            'email' => $anonymous ? null : ($variables['email'] ?? null),
            'telefone' => $anonymous ? null : $this->formatPhone(
                $variables['telefone'] ?? $conversation->contact->wa_id ?? ''
            ),
            'tipo' => $variables['tipo'],
            'categoria' => $variables['categoria'] ?? null,
            'assunto' => $variables['assunto'],
            'descricao' => $variables['descricao'],
            'endereco' => $variables['endereco'] ?? null,
            'bairro' => $variables['bairro'] ?? null,
            'referencia' => $variables['referencia'] ?? null,
        ], fn ($value): bool => $value !== null);

        $response = Http::withOptions(['cookies' => $cookies])
            ->timeout($timeout)
            ->post($url, $fields);

        if (! $response->successful()) {
            return ['success' => false, 'error' => "A ouvidoria respondeu HTTP {$response->status()}."];
        }

        $body = $response->body();
        $errors = $this->extractFormErrors($body);
        if ($errors !== []) {
            return ['success' => false, 'error' => 'O GED recusou os dados: '.implode(' ', $errors)];
        }

        // Página de recibo (/ouvidoria/recibo): rótulo em <div> seguido do valor.
        $protocolo = $this->extractPattern(
            $body,
            '/Protocolo<\/div>\s*<div[^>]*>\s*(\d{4,14})\s*<\/div>/i',
        );
        $codigo = $this->extractPattern(
            $body,
            '/Código de acompanhamento<\/div>\s*<div[^>]*>\s*([A-Za-z0-9]{4,20})\s*<\/div>/i',
        );

        $receipt = str_contains($body, 'registrada com sucesso')
            || str_contains((string) $response->effectiveUri(), '/recibo');

        if ($protocolo === null && ! $receipt) {
            return ['success' => false, 'error' => 'Envio aceito, mas o protocolo não foi localizado na resposta.'];
        }

        $resultVariables = array_filter([
            'ouvidoria_protocolo' => $protocolo,
            'ouvidoria_codigo' => $codigo,
            'ouvidoria_enviada' => '1',
        ]);

        return [
            'success' => true,
            'variables' => $resultVariables,
            'summary' => 'Manifestação registrada'.($protocolo ? " — protocolo {$protocolo}" : ' sem leitura do protocolo'),
        ];
    }

    private function extractFormErrors(string $body): array
    {
        if (! preg_match_all(
            '/<[^>]+class="[^"]*(?:invalid-feedback|alert-danger|text-danger)[^"]*"[^>]*>(.*?)<\//s',
            $body,
            $matches,
        )) {
            return [];
        }

        return collect($matches[1])
            ->map(fn (string $html): string => trim(html_entity_decode(strip_tags($html))))
            ->filter()
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    private function extractPattern(string $body, string $pattern): ?string
    {
        return preg_match($pattern, $body, $match) ? $match[1] : null;
    }

    private function formatCpf(?string $cpf): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $cpf);
        if (strlen($digits) !== 11) {
            return $cpf;
        }

        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
    }

    private function formatPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits);
        }

        if (strlen($digits) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits);
        }

        return $digits;
    }
}
