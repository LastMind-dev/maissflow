<?php

namespace App\Services;

class AcompanhamentoFlowFactory
{
    public const ENTRY_NODE = 'ac_intro';

    /**
     * Ramo de consulta de andamento: o cidadão informa protocolo + código de
     * acompanhamento (a mesma credencial do portal público) e a ação
     * 'ouvidoria.consulta' busca status/resposta na API do MAISSDoc.
     *
     * @return array{nodes: array, edges: array, option: array}
     */
    public function branch(string $menuId = 'menu_main', string $optionId = 'acompanhamento'): array
    {
        $node = static fn (
            string $id,
            string $type,
            int $x,
            int $y,
            array $data,
        ): array => [
            'id' => $id,
            'type' => $type,
            'position' => ['x' => $x, 'y' => $y],
            'data' => $data,
        ];

        $edge = static fn (string $id, string $source, string $target, ?string $handle = null): array => array_filter([
            'id' => $id,
            'source' => $source,
            'target' => $target,
            'sourceHandle' => $handle,
            'data' => $handle ? ['optionKey' => $handle] : null,
            'type' => 'smoothstep',
        ], static fn ($value): bool => $value !== null);

        $nodes = [
            $node(self::ENTRY_NODE, 'message', 1280, 8800, [
                'label' => 'Consulta: introdução',
                'text' => "🔎 *Acompanhar manifestação ou pedido*\n\nVou consultar o andamento direto no sistema da Prefeitura. Tenha em mãos o *protocolo* e o *código de acompanhamento* que você recebeu no registro.",
                'continueLabel' => 'Consultar',
            ]),
            $node('ac_protocolo', 'input', 1700, 8700, [
                'label' => 'Consulta: protocolo',
                'text' => 'Informe o *número do protocolo* (ex.: 2026000123):',
                'variable' => 'consulta_protocolo',
                'validation' => 'any',
                'maxLength' => 20,
            ]),
            $node('ac_codigo', 'input', 1700, 8860, [
                'label' => 'Consulta: código',
                'text' => 'Agora informe o *código de acompanhamento* enviado no recibo:',
                'variable' => 'consulta_codigo',
                'validation' => 'any',
                'maxLength' => 20,
            ]),
            $node('ac_consulta', 'action', 2140, 8780, [
                'label' => 'Consultar andamento',
                'action' => 'ouvidoria.consulta',
            ]),
            $node('ac_resultado', 'message', 2580, 8660, [
                'label' => 'Consulta: resultado',
                'text' => "📄 *Protocolo {{flow.consulta_protocolo}}*\n\n*Tipo:* {{flow.consulta_tipo_label}}\n*Assunto:* {{flow.consulta_assunto}}\n*Situação:* {{flow.consulta_status_label}}\n*Registrado em:* {{flow.consulta_criado_em}}\n*Prazo de resposta:* {{flow.consulta_prazo}}\n*Setor responsável:* {{flow.consulta_setor}}\n*Anexos:* {{flow.consulta_anexos_total}}\n\n*Resposta:*\n{{flow.consulta_resposta}}",
                'links' => [
                    ['label' => 'Ver detalhes no portal', 'url' => 'https://prdmaissdoc.fgmaiss.com.br/consulta'],
                ],
            ]),
            $node('ac_erro', 'message', 2580, 8960, [
                'label' => 'Consulta: erro',
                'text' => "⚠️ *Não foi possível localizar o andamento.*\n\n{{flow.consulta_motivo}}",
                'links' => [
                    ['label' => 'Consultar no portal', 'url' => 'https://prdmaissdoc.fgmaiss.com.br/consulta'],
                ],
            ]),
        ];

        $edges = [
            $edge('e-ac-entrada', $menuId, self::ENTRY_NODE, $optionId),
            $edge('e-ac-intro-protocolo', self::ENTRY_NODE, 'ac_protocolo'),
            $edge('e-ac-protocolo-codigo', 'ac_protocolo', 'ac_codigo'),
            $edge('e-ac-codigo-consulta', 'ac_codigo', 'ac_consulta'),
            $edge('e-ac-consulta-ok', 'ac_consulta', 'ac_resultado', 'success'),
            $edge('e-ac-consulta-erro', 'ac_consulta', 'ac_erro', 'error'),
            $edge('e-ac-resultado-menu', 'ac_resultado', $menuId),
            $edge('e-ac-erro-menu', 'ac_erro', $menuId),
        ];

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'option' => [
                'id' => $optionId,
                'label' => '🔎 Acompanhar pedido',
                'description' => 'Andamento de manifestação ou pedido e-SIC',
            ],
        ];
    }
}
