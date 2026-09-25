<?php

namespace App\Services\Actions;

use App\Models\Conversation;
use App\Models\FlowExecution;
use App\Services\GedPortalClient;

/**
 * Consulta o andamento de uma manifestação/pedido no MAISSDoc pelo par
 * protocolo + código de acompanhamento (mesma credencial do portal público).
 * O código funciona como senha — a API responde 404 genérico para par
 * inválido, sem distinguir causa.
 */
class OuvidoriaConsultaAction implements FlowAction
{
    private const STATUS_LABELS = [
        'aberto' => 'Aberta',
        'em_analise' => 'Em análise',
        'respondido' => 'Respondida',
        'concluido' => 'Concluída',
        'arquivado' => 'Arquivada',
    ];

    public function id(): string
    {
        return 'ouvidoria.consulta';
    }

    public function label(): string
    {
        return 'Consultar andamento (MAISSDoc)';
    }

    public function description(): string
    {
        return 'Consulta status e resposta de uma manifestação pelo protocolo + código e expõe {{flow.consulta_status}}, {{flow.consulta_resposta}} e demais campos.';
    }

    public function simulatedVariables(): array
    {
        return [
            'consulta_status' => 'em_analise',
            'consulta_status_label' => 'Em análise',
            'consulta_tipo_label' => 'Ouvidoria — reclamação',
            'consulta_assunto' => 'Assunto simulado',
            'consulta_criado_em' => '01/01/2026',
            'consulta_prazo' => '10/01/2026',
            'consulta_setor' => 'Setor responsável',
            'consulta_resposta' => '',
        ];
    }

    public function execute(array $config, Conversation $conversation, FlowExecution $execution): array
    {
        $variables = $execution->context['variables'] ?? [];

        $protocolo = preg_replace('/\D/', '', (string) (
            $variables['consulta_protocolo'] ?? $variables['ouvidoria_protocolo'] ?? ''
        ));
        $codigo = strtoupper(trim((string) (
            $variables['consulta_codigo'] ?? $variables['ouvidoria_codigo'] ?? ''
        )));

        if ($protocolo === '' || $codigo === '') {
            return ['success' => false, 'error' => 'Protocolo ou código de acompanhamento não informados.'];
        }

        $client = app(GedPortalClient::class);
        if (! $client->disponivel()) {
            return ['success' => false, 'error' => 'GED_API_URL/GED_API_TOKEN não configurados — consulta indisponível.'];
        }

        $result = $client->consultar($protocolo, $codigo);

        if (! $result['success']) {
            $motivo = $result['status'] === 404
                ? 'Protocolo ou código não localizados. Confira os dados enviados no recibo e tente novamente.'
                : 'Não consegui consultar o andamento agora. Tente de novo em instantes ou acesse o portal.';

            return [
                'success' => false,
                'variables' => ['consulta_motivo' => $motivo],
                'error' => $motivo,
            ];
        }

        $canal = (string) data_get($result, 'data.canal', 'ouvidoria');
        $tipo = (string) data_get($result, 'data.tipo', '');
        $status = (string) data_get($result, 'data.status', '');

        return [
            'success' => true,
            'variables' => array_filter([
                'consulta_protocolo' => $protocolo,
                'consulta_status' => $status,
                'consulta_status_label' => (string) (data_get($result, 'data.status_label')
                    ?: (self::STATUS_LABELS[$status] ?? $status)),
                'consulta_tipo_label' => $canal === 'esic'
                    ? 'Pedido e-SIC'
                    : ($tipo !== '' ? 'Ouvidoria — '.$tipo : 'Ouvidoria'),
                'consulta_assunto' => data_get($result, 'data.assunto') ?: '—',
                'consulta_criado_em' => $this->formatDate(data_get($result, 'data.criado_em')) ?? '—',
                'consulta_prazo' => $this->formatDate(data_get($result, 'data.data_limite')) ?? '—',
                'consulta_setor' => data_get($result, 'data.setor') ?: '—',
                'consulta_resposta' => trim((string) data_get($result, 'data.resposta', ''))
                    ?: 'Ainda sem resposta registrada.',
                'consulta_resultado' => data_get($result, 'data.resultado_label'),
                'consulta_atrasada' => data_get($result, 'data.atrasada') ? '1' : null,
                'consulta_pode_recorrer' => data_get($result, 'data.pode_recorrer') ? '1' : null,
                'consulta_anexos_total' => (string) (data_get($result, 'data.anexos_total') ?? 0),
                'consulta_motivo' => null,
            ], fn ($value): bool => $value !== null),
            'summary' => "Consulta {$protocolo}: ".($status !== '' ? $status : 'sem status'),
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('d/m/Y', $timestamp);
    }
}
