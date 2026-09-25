<?php

namespace App\Services;

class OuvidoriaFlowFactory
{
    public const ENTRY_NODE = 'ouv_intro';

    /**
     * Ramo de coleta da ouvidoria (MAISSDoc), pronto para ser pendurado em um
     * menu existente. Retorna os nós, as conexões internas e a opção de menu
     * que aponta para a entrada do ramo.
     *
     * @return array{nodes: array, edges: array, option: array}
     */
    public function branch(string $menuId = 'menu_main', string $optionId = 'ouvidoria'): array
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
            $node(self::ENTRY_NODE, 'message', 1280, 5600, [
                'label' => 'Ouvidoria: introdução',
                'text' => "🏛️ *Ouvidoria — Prefeitura Municipal*\n\nVou registrar sua manifestação (reclamação, denúncia, sugestão, elogio ou solicitação) direto na ouvidoria.\n\nResponda às perguntas a seguir. Nos campos opcionais, envie *0* para pular.\n\n📎 No final você poderá anexar fotos ou documentos, se quiser.",
                'continueLabel' => 'Iniciar registro',
            ]),
            $node('ouv_ident', 'menu', 1700, 5500, [
                'label' => 'Ouvidoria: identificação',
                'text' => 'Você prefere se identificar ou enviar de forma anônima?',
                'menuMode' => 'buttons',
                'saveTo' => 'identificacao',
                'options' => [
                    ['id' => 'identificado', 'label' => 'Me identificar'],
                    ['id' => 'anonimo', 'label' => 'Enviar anônimo'],
                ],
            ]),
            $node('ouv_nome', 'input', 2140, 5300, [
                'label' => 'Ouvidoria: nome',
                'text' => 'Informe seu *nome completo*:',
                'variable' => 'nome',
                'validation' => 'text',
                'maxLength' => 255,
            ]),
            $node('ouv_cpf', 'input', 2140, 5460, [
                'label' => 'Ouvidoria: CPF',
                'text' => 'Informe seu *CPF* (000.000.000-00):',
                'variable' => 'cpf',
                'validation' => 'cpf',
                'maxLength' => 14,
                'retryText' => 'CPF inválido. Confira os números e envie novamente:',
            ]),
            $node('ouv_email', 'input', 2140, 5620, [
                'label' => 'Ouvidoria: e-mail',
                'text' => 'Informe seu *e-mail* (obrigatório — a ouvidoria responde por ele):',
                'variable' => 'email',
                'validation' => 'email',
                'maxLength' => 255,
                'retryText' => 'E-mail inválido. Envie um endereço válido (ex.: nome@provedor.com):',
            ]),
            $node('ouv_tipo', 'menu', 2140, 5840, [
                'label' => 'Ouvidoria: tipo',
                'text' => 'Qual o *tipo* da sua manifestação?',
                'buttonLabel' => 'Ver tipos',
                'sectionTitle' => 'Tipos',
                'menuMode' => 'list',
                'saveTo' => 'tipo',
                'options' => [
                    ['id' => 'reclamacao', 'label' => 'Reclamação'],
                    ['id' => 'denuncia', 'label' => 'Denúncia'],
                    ['id' => 'sugestao', 'label' => 'Sugestão'],
                    ['id' => 'elogio', 'label' => 'Elogio'],
                    ['id' => 'solicitacao', 'label' => 'Solicitação'],
                ],
            ]),
            $node('ouv_categoria', 'input', 2580, 5840, [
                'label' => 'Ouvidoria: área',
                'text' => "Qual a *área/assunto*? (ex.: iluminação, saúde, vias)\nEnvie *0* para pular.",
                'variable' => 'categoria',
                'validation' => 'any',
                'optional' => true,
                'maxLength' => 50,
            ]),
            $node('ouv_assunto', 'input', 2580, 6000, [
                'label' => 'Ouvidoria: assunto',
                'text' => 'Escreva um *resumo* da manifestação (título):',
                'variable' => 'assunto',
                'validation' => 'text',
                'maxLength' => 255,
            ]),
            $node('ouv_descricao', 'input', 2580, 6160, [
                'label' => 'Ouvidoria: descrição',
                'text' => 'Agora *descreva o que aconteceu*, com o máximo de detalhes:',
                'variable' => 'descricao',
                'validation' => 'text',
                'maxLength' => 5000,
            ]),
            $node('ouv_endereco', 'input', 2580, 6320, [
                'label' => 'Ouvidoria: endereço',
                'text' => "Informe o *endereço do fato* (rua, número).\nEnvie *0* para pular.",
                'variable' => 'endereco',
                'validation' => 'any',
                'optional' => true,
                'maxLength' => 255,
            ]),
            $node('ouv_bairro', 'input', 2580, 6480, [
                'label' => 'Ouvidoria: bairro',
                'text' => "Informe o *bairro*.\nEnvie *0* para pular.",
                'variable' => 'bairro',
                'validation' => 'any',
                'optional' => true,
                'maxLength' => 100,
            ]),
            $node('ouv_referencia', 'input', 2580, 6640, [
                'label' => 'Ouvidoria: referência',
                'text' => "Algum *ponto de referência*?\nEnvie *0* para pular.",
                'variable' => 'referencia',
                'validation' => 'any',
                'optional' => true,
                'maxLength' => 255,
            ]),
            $node('ouv_anexos', 'input', 2580, 6800, [
                'label' => 'Ouvidoria: anexos',
                'text' => "Se quiser, envie agora *fotos ou documentos* como anexo (até 5 arquivos, um por mensagem).\n\nQuando terminar — ou se não tiver anexos — envie *0*.",
                'variable' => 'anexos',
                'validation' => 'media',
                'maxItems' => 5,
                'retryText' => 'Envie a foto ou o documento como anexo do WhatsApp, ou *0* para concluir.',
            ]),
            $node('ouv_confirma', 'message', 3020, 6400, [
                'label' => 'Ouvidoria: confirmação',
                'text' => "📋 *Confira os dados:*\n\n*Tipo:* {{flow.tipo_label}}\n*Assunto:* {{flow.assunto}}\n*Descrição:* {{flow.descricao}}\n*Nome:* {{flow.nome}}\n*Telefone:* {{contact.phone}}\n*Anexos:* {{flow.anexos_total}} arquivo(s)\n\nToque em *Confirmar envio* para registrar na ouvidoria.",
                'continueLabel' => 'Confirmar envio',
            ]),
            $node('ouv_submit', 'action', 3460, 6400, [
                'label' => 'Registrar na ouvidoria',
                'action' => 'ouvidoria.submit',
            ]),
            $node('ouv_sucesso', 'message', 3900, 6220, [
                'label' => 'Ouvidoria: sucesso',
                'text' => "✅ *Manifestação registrada na ouvidoria!*\n\n*Protocolo:* {{flow.ouvidoria_protocolo}}\n*Código de acompanhamento:* {{flow.ouvidoria_codigo}}\n\nGuarde esses dados para consultar a resposta.",
                'links' => [
                    ['label' => 'Acompanhar manifestação', 'url' => 'https://prdmaissdoc.fgmaiss.com.br/consulta'],
                ],
            ]),
            $node('ouv_erro', 'message', 3900, 6600, [
                'label' => 'Ouvidoria: erro',
                'text' => "⚠️ *Não consegui registrar sua manifestação agora.*\n\nVocê pode tentar direto pelo site da ouvidoria ou falar com nosso suporte.",
                'links' => [
                    ['label' => 'Abrir ouvidoria', 'url' => 'https://prdmaissdoc.fgmaiss.com.br/ouvidoria'],
                ],
            ]),
        ];

        $edges = [
            $edge('e-ouv-entrada', $menuId, self::ENTRY_NODE, $optionId),
            $edge('e-ouv-intro-ident', self::ENTRY_NODE, 'ouv_ident'),
            $edge('e-ouv-ident-sim', 'ouv_ident', 'ouv_nome', 'identificado'),
            $edge('e-ouv-ident-anon', 'ouv_ident', 'ouv_tipo', 'anonimo'),
            $edge('e-ouv-nome-cpf', 'ouv_nome', 'ouv_cpf'),
            $edge('e-ouv-cpf-email', 'ouv_cpf', 'ouv_email'),
            $edge('e-ouv-email-tipo', 'ouv_email', 'ouv_tipo'),
            $edge('e-ouv-tipo-reclamacao', 'ouv_tipo', 'ouv_categoria', 'reclamacao'),
            $edge('e-ouv-tipo-denuncia', 'ouv_tipo', 'ouv_categoria', 'denuncia'),
            $edge('e-ouv-tipo-sugestao', 'ouv_tipo', 'ouv_categoria', 'sugestao'),
            $edge('e-ouv-tipo-elogio', 'ouv_tipo', 'ouv_categoria', 'elogio'),
            $edge('e-ouv-tipo-solicitacao', 'ouv_tipo', 'ouv_categoria', 'solicitacao'),
            $edge('e-ouv-categoria-assunto', 'ouv_categoria', 'ouv_assunto'),
            $edge('e-ouv-assunto-descricao', 'ouv_assunto', 'ouv_descricao'),
            $edge('e-ouv-descricao-endereco', 'ouv_descricao', 'ouv_endereco'),
            $edge('e-ouv-endereco-bairro', 'ouv_endereco', 'ouv_bairro'),
            $edge('e-ouv-bairro-referencia', 'ouv_bairro', 'ouv_referencia'),
            $edge('e-ouv-referencia-anexos', 'ouv_referencia', 'ouv_anexos'),
            $edge('e-ouv-anexos-confirma', 'ouv_anexos', 'ouv_confirma'),
            $edge('e-ouv-confirma-submit', 'ouv_confirma', 'ouv_submit'),
            $edge('e-ouv-submit-ok', 'ouv_submit', 'ouv_sucesso', 'success'),
            $edge('e-ouv-submit-erro', 'ouv_submit', 'ouv_erro', 'error'),
            $edge('e-ouv-sucesso-menu', 'ouv_sucesso', $menuId),
            $edge('e-ouv-erro-menu', 'ouv_erro', $menuId),
        ];

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'option' => [
                'id' => $optionId,
                'label' => '🏛️ Ouvidoria',
                'description' => 'Reclamação, denúncia, sugestão ou elogio à Prefeitura',
            ],
        ];
    }
}
