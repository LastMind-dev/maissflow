<?php

namespace App\Services;

class EsicFlowFactory
{
    public const ENTRY_NODE = 'esic_intro';

    /**
     * Ramo de pedido de acesso à informação (e-SIC/LAI) no MAISSDoc. A LAI
     * exige identificação: não há opção anônima e o CPF é obrigatório. O
     * envio usa a mesma ação da ouvidoria com canal 'esic' na API do GED.
     *
     * @return array{nodes: array, edges: array, option: array}
     */
    public function branch(string $menuId = 'menu_main', string $optionId = 'esic'): array
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
            $node(self::ENTRY_NODE, 'message', 1280, 7200, [
                'label' => 'e-SIC: introdução',
                'text' => "📄 *e-SIC — Pedido de Acesso à Informação*\n\nSolicite informações públicas ao município, conforme a *Lei nº 12.527/2011 (LAI)*.\n\n⚠️ *A lei exige identificação* — não é possível pedir de forma anônima. Você vai precisar de *nome completo*, *CPF* e *e-mail*.\n\n⏱️ A resposta tem *prazo legal* (em geral até 20 dias, prorrogável por igual período). Ao concluir você recebe *protocolo* + *código de acompanhamento*.",
                'continueLabel' => 'Iniciar pedido',
            ]),
            $node('esic_nome', 'input', 1700, 7100, [
                'label' => 'e-SIC: nome',
                'text' => 'Informe seu *nome completo*:',
                'variable' => 'nome',
                'validation' => 'text',
                'maxLength' => 255,
            ]),
            $node('esic_cpf', 'input', 1700, 7260, [
                'label' => 'e-SIC: CPF',
                'text' => 'Informe seu *CPF* (000.000.000-00):',
                'variable' => 'cpf',
                'validation' => 'cpf',
                'maxLength' => 14,
                'retryText' => 'CPF inválido. Confira os números e envie novamente:',
            ]),
            $node('esic_email', 'input', 1700, 7420, [
                'label' => 'e-SIC: e-mail',
                'text' => 'Informe seu *e-mail* (a resposta do pedido também é enviada por ele):',
                'variable' => 'email',
                'validation' => 'email',
                'maxLength' => 255,
                'retryText' => 'E-mail inválido. Envie um endereço válido (ex.: nome@provedor.com):',
            ]),
            $node('esic_assunto', 'input', 2140, 7260, [
                'label' => 'e-SIC: assunto',
                'text' => 'Escreva um *resumo do que você quer saber* (título do pedido):',
                'variable' => 'assunto',
                'validation' => 'text',
                'maxLength' => 255,
            ]),
            $node('esic_descricao', 'input', 2140, 7420, [
                'label' => 'e-SIC: descrição',
                'text' => 'Agora *detalhe a informação solicitada*, com o máximo de dados para localizá-la:',
                'variable' => 'descricao',
                'validation' => 'text',
                'maxLength' => 5000,
            ]),
            $node('esic_anexos', 'input', 2140, 7580, [
                'label' => 'e-SIC: anexos',
                'text' => "Se quiser, envie *documentos ou imagens de referência* como anexo — até *5 arquivos* de 10 MB cada (PDF, imagens, DOC, DOCX, XLS...), um por mensagem.\n\nQuando terminar — ou se não tiver anexos — envie *0*.",
                'variable' => 'anexos',
                'validation' => 'media',
                'maxItems' => 5,
                'retryText' => 'Envie o arquivo como anexo do WhatsApp, ou *0* para concluir.',
            ]),
            $node('esic_confirma', 'message', 2580, 7420, [
                'label' => 'e-SIC: confirmação',
                'text' => "📋 *Confira os dados do pedido:*\n\n*Nome:* {{flow.nome}}\n*CPF:* {{flow.cpf}}\n*E-mail:* {{flow.email}}\n*Assunto:* {{flow.assunto}}\n*Descrição:* {{flow.descricao}}\n*Anexos:* {{flow.anexos_total}} arquivo(s)\n\nToque em *Enviar pedido* para registrar no e-SIC.",
                'continueLabel' => 'Enviar pedido',
            ]),
            $node('esic_submit', 'action', 3020, 7420, [
                'label' => 'Registrar no e-SIC',
                'action' => 'ouvidoria.submit',
                'canal' => 'esic',
            ]),
            $node('esic_sucesso', 'message', 3460, 7240, [
                'label' => 'e-SIC: sucesso',
                'text' => "✅ *Pedido registrado com sucesso!*\n\n*Protocolo:* {{flow.ouvidoria_protocolo}}\n*Código de acompanhamento:* {{flow.ouvidoria_codigo}}\n\n⚠️ *Guarde os dois juntos* — eles não podem ser recuperados depois.\n\nSeu pedido de acesso à informação foi recebido e será respondido dentro do *prazo legal*. Se a resposta for negativa ou parcial — ou não chegar no prazo — você pode *recorrer em até 10 dias* pelo portal.\n\nPara acompanhar, use a opção *Acompanhar pedido* do menu ou o link abaixo.",
                'links' => [
                    ['label' => 'Acompanhar pedido', 'url' => 'https://prdmaissdoc.fgmaiss.com.br/consulta'],
                ],
            ]),
            $node('esic_erro', 'message', 3460, 7620, [
                'label' => 'e-SIC: erro',
                'text' => "⚠️ *Não consegui registrar seu pedido agora.*\n\nVocê pode tentar direto pelo portal e-SIC ou falar com nosso suporte.",
                'links' => [
                    ['label' => 'Abrir portal e-SIC', 'url' => 'https://prdmaissdoc.fgmaiss.com.br/esic'],
                ],
            ]),
        ];

        $edges = [
            $edge('e-esic-entrada', $menuId, self::ENTRY_NODE, $optionId),
            $edge('e-esic-intro-nome', self::ENTRY_NODE, 'esic_nome'),
            $edge('e-esic-nome-cpf', 'esic_nome', 'esic_cpf'),
            $edge('e-esic-cpf-email', 'esic_cpf', 'esic_email'),
            $edge('e-esic-email-assunto', 'esic_email', 'esic_assunto'),
            $edge('e-esic-assunto-descricao', 'esic_assunto', 'esic_descricao'),
            $edge('e-esic-descricao-anexos', 'esic_descricao', 'esic_anexos'),
            $edge('e-esic-anexos-confirma', 'esic_anexos', 'esic_confirma'),
            $edge('e-esic-confirma-submit', 'esic_confirma', 'esic_submit'),
            $edge('e-esic-submit-ok', 'esic_submit', 'esic_sucesso', 'success'),
            $edge('e-esic-submit-erro', 'esic_submit', 'esic_erro', 'error'),
            $edge('e-esic-sucesso-menu', 'esic_sucesso', $menuId),
            $edge('e-esic-erro-menu', 'esic_erro', $menuId),
        ];

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'option' => [
                'id' => $optionId,
                'label' => '📄 Pedido e-SIC',
                'description' => 'Acesso à informação pública (LAI) — exige CPF',
            ],
        ];
    }
}
