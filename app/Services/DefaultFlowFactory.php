<?php

namespace App\Services;

class DefaultFlowFactory
{
    public function make(): array
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

        $link = static fn (string $url, string $label = ''): array => [
            'label' => $label,
            'url' => $url,
        ];

        $nodes = [
            $node('trigger_incoming', 'trigger', 40, 2320, [
                'label' => 'Quando o usuário enviar uma mensagem',
                'triggerType' => 'incoming_message',
            ]),
            $node('menu_main', 'menu', 420, 2260, [
                'label' => 'Enviar Mensagem',
                'introText' => "Olá! Bem-vindo ao Suporte Online! 😄 Para facilitar, selecione abaixo o assunto que gostaria de tratar. Estou aqui para ajudar!\n",
                'header' => 'Selecione a Opção Desejada',
                'text' => 'Selecione',
                'buttonLabel' => 'Selecione uma Opção',
                'sectionTitle' => 'Menu',
                'menuMode' => 'list',
                'options' => [
                    ['id' => 'nfe', 'label' => '🏬 NF-E', 'description' => 'Nota Fiscal Eletrônica'],
                    ['id' => 'vaf', 'label' => '🏙️ VAF', 'description' => 'Atendimento do VAF - Valor Adicionado Fiscal'],
                    ['id' => 'declaracoes', 'label' => '📄 Declarações ', 'description' => 'Declarações de Serviços Prestados ou Tomados'],
                    ['id' => 'cadastros', 'label' => '👨‍💼 Cadastros', 'description' => 'Destinatários e Tomadores'],
                    ['id' => 'senha', 'label' => '🔑 Senha', 'description' => 'Senha Bloqueada ou Alteração'],
                    ['id' => 'ouvidoria', 'label' => '🏛️ Ouvidoria', 'description' => 'Reclamação, denúncia, sugestão ou elogio à Prefeitura'],
                    ['id' => 'esic', 'label' => '📄 Pedido e-SIC', 'description' => 'Acesso à informação pública (LAI) — exige CPF'],
                    ['id' => 'acompanhamento', 'label' => '🔎 Acompanhar pedido', 'description' => 'Andamento de manifestação ou pedido e-SIC'],
                    ['id' => 'suporte', 'label' => '🆘 Suporte', 'description' => 'Falar com Suporte'],
                ],
            ]),

            $node('menu_nfe', 'menu', 840, 180, [
                'label' => 'Enviar Mensagem #1',
                'text' => '🏬 Nota Fiscal Eletrônica',
                'buttonLabel' => 'Selecione uma Opção',
                'sectionTitle' => 'Menu',
                'menuMode' => 'list',
                'options' => [
                    ['id' => 'emissao', 'label' => 'Emissão', 'description' => 'Como emitir Nota Fiscal Eletrônica'],
                    ['id' => 'webservice', 'label' => 'Webservice', 'description' => 'Como emitir Nota Fiscal via WEBSERVICE'],
                    ['id' => 'cancelamento', 'label' => 'Cancelamento', 'description' => 'Como cancelar uma Nota Fiscal'],
                    ['id' => 'logo', 'label' => 'Inserir logo', 'description' => 'Inserir logo da empresa na nota fiscal'],
                    ['id' => 'xml', 'label' => 'Baixar XML', 'description' => 'Baixar XML das Notas Fiscais'],
                    ['id' => 'boleto', 'label' => 'Imprimir Boleto', 'description' => 'Imprimir Boleto de Pagamento'],
                    ['id' => 'faturamento', 'label' => 'Faturamento Bruto', 'description' => 'Faturamento Bruto Informado Errado, como corrigir?'],
                    ['id' => 'autenticidade', 'label' => 'Autenticidade NFSE', 'description' => 'Autenticidade Nota Fiscal e Carta de Correção'],
                    ['id' => 'voltar', 'label' => 'Voltar'],
                ],
            ]),
            $node('nfe_emissao', 'message', 1280, -720, [
                'label' => 'Enviar Mensagem #2',
                'text' => 'Tutoriais para emissão de Nota Fiscal Eletrônica',
                'messages' => [
                    [
                        'text' => 'Como Emitir Nota Prestador Não Optante SN',
                        'links' => [$link('https://www.dropbox.com/scl/fi/kygs7xhc8im8r8jfsy5dk/Como-Emitir-Nota-Prestador-N-o-Optante-SN.mkv?rlkey=7x68u6gtfgkl7mnolazni3ex8&dl=0', 'Download')],
                    ],
                    [
                        'text' => 'Como Emitir Nota Prestador Optante SN',
                        'links' => [$link('https://www.dropbox.com/scl/fi/e7wjt5xheqsxmje09at67/Como-Emitir-Nota-Prestador-Optante-SN.mkv?rlkey=nyyo2krt0vk63o63w2jnknlsh&dl=0', 'Download')],
                    ],
                    [
                        'text' => 'Como Emitir Nota Exportação com ISSQN',
                        'links' => [$link('https://www.dropbox.com/scl/fi/ughu70usrg9be467o0w21/Como-Emitir-Nota-Exporta-o-com-ISSQN.mkv?rlkey=e8pzzif0wqqzgpgqxq1wzleju&dl=0', 'Download')],
                    ],
                    [
                        'text' => 'Como Emitir Nota Exportação Isento ISSQN',
                        'links' => [$link('https://www.dropbox.com/scl/fi/10xfoymcrtql12rmtqwka/Como-Emitir-Nota-Exporta-o-Isento-ISSQN.mkv?rlkey=6hoggn5kwg0tralc0tjczmyev&dl=0', 'Download')],
                    ],
                    [
                        'text' => 'Como Emitir Nota de Obra',
                        'links' => [$link('https://www.dropbox.com/scl/fi/he3ufxmbznsseak0oc3bn/Como-Emitir-Nota-de-Obra.mkv?rlkey=ihrm1nekb9me33gqrows86mot&dl=0', 'Download')],
                    ],
                    [
                        'text' => 'Como Emitir Nota de Evento',
                        'links' => [$link('https://www.dropbox.com/scl/fi/90tfhjjqqnc7msiz8z3js/Como-Emitir-Nota-de-Evento.mkv?rlkey=1rr1c3zfshp7i0qn70z57drwo&dl=0', 'Download')],
                    ],
                    [
                        'text' => 'Informação Regime Tributário Inicio Mês',
                        'links' => [$link('https://www.dropbox.com/scl/fi/5ehjwkpe8rqds6b2xlo6k/Informa-o-Regime-Tribut-rio-Inicio-M-s.mkv?rlkey=dkyvo4ju1ldg94o79jy71k8r0&dl=0', 'Download')],
                    ],
                ],
            ]),
            $node('menu_webservice', 'menu', 1280, 140, [
                'label' => 'Emitir via Webservice',
                'text' => '🖥️ Como Emitir Nota Fiscal via WEBSERVICE ',
                'buttonLabel' => 'Selecione uma Opção',
                'sectionTitle' => 'Título da seção',
                'menuMode' => 'list',
                'options' => [
                    ['id' => 'sp_ate_2025', 'label' => 'SP até 31/12/2025', 'description' => 'Prestador do Estado de São Paulo'],
                    ['id' => 'sp_desde_2026', 'label' => 'SP a partir 01/01/26', 'description' => 'Prestador do Estado de São Paulo'],
                    ['id' => 'rj_ate_2025', 'label' => 'RJ até 31/12/2025', 'description' => 'Prestador do Estado do Rio de Janeiro'],
                    ['id' => 'rj_desde_2026', 'label' => 'RJ a partir 01/01/26', 'description' => 'Prestador do Estado do Rio de Janeiro'],
                    ['id' => 'voltar', 'label' => 'Voltar'],
                ],
            ]),
            $node('ws_sp_2025', 'message', 1740, -220, [
                'label' => 'Webservice SP até 2025',
                'text' => "LAYOUT WSNFSE-SP -\nCÓDIGOS MUNICÍPIOS -\nMODELO XML ENVIO NFSE - FORA DO SIMPLES NACIONAL:\nMODELO XML CONSULTA E CANCELAMENTO:\nEMITIR NOTA SERVIÇO EXTERIOR ALÍQUOTA ZERO:\nEXPLICANDO HOMOLOGAÇÃO WEBSERVICE NFSE:",
                'links' => [
                    $link('https://www.dropbox.com/s/pzpofcfo6kmtveu/LAYOUT%20WSNFSE-SP.pdf?dl=0', 'LAYOUT WSNFSE-SP'),
                    $link('https://www.dropbox.com/s/g8kfomhghu4d4dk/C%C3%93DIGOS%20MUNIC%C3%8DPIOS.txt?dl=0', 'CÓDIGOS MUNICÍPIOS'),
                    $link('https://www.dropbox.com/scl/fi/zoi1h0xrnyks2xlxkgr07/modelo-xml-envio-nfse-fora-do-simples-nacional.xml?e=2&mcp_token=eyJwaWQiOjEyMzc4NDAsInNpZCI6MjEwNjM0MDE0LCJheCI6ImQxOTZmNDBjNWI3YWU5ZTllYWNhMDZjNGM1MWJjY2IwIiwidHMiOjE3MjU1NDYzNTMsImV4cCI6MTcyNzk2NTU1M30.3GpdpDtYLnIXETWs8aOKxqtPQP6kaf3gnq5gCTL0zgw&rlkey=tb8c895qetkocztgbgj12hcf7&dl=0', 'MODELO XML ENVIO NFSE'),
                    $link('https://www.dropbox.com/s/vexk3xd4ovpvfsf/Modelo%20xml%20Consulta%20e%20de%20Cancelamento.pdf?dl=0', 'MODELO XML CONSULTA E CANCELAMENTO'),
                    $link('https://www.dropbox.com/scl/fi/4r8f0bjpyek6tmrzfwt1r/Emitir-Nota-Servi-o-Exterior-Aliquota-Zero.mkv?rlkey=vmrc4qt2u4f0oxnzsb64yvg7i&dl=0', 'EMITIR NOTA SERVIÇO EXTERIOR'),
                    $link('https://www.dropbox.com/scl/fi/tgdpy3pn2kvqssfttt84x/Explicando-Homologa-o-WebService-NFSe.mkv?rlkey=b9z4623mnagjdlw3hpovflj3e&dl=0', 'EXPLICANDO HOMOLOGAÇÃO'),
                ],
            ]),
            $node('ws_sp_2026', 'message', 1740, 240, [
                'label' => 'Webservice SP 2026',
                'text' => 'WebService2026-SP📇:',
                'links' => [$link('https://www.dropbox.com/scl/fi/68qt7geueyjyzyr5kdn82/WebService2026-SP.rar?rlkey=cyvtq9ynp9buj68g93vzhnav1&dl=0')],
            ]),
            $node('ws_rj_2025', 'message', 1740, 460, [
                'label' => 'Webservice RJ até 2025',
                'text' => "LAYOUT WSNFSE-RJ -\nCÓDIGOS MUNICÍPIOS -\nMODELO XML ENVIO NFSE - FORA DO SIMPLES NACIONAL:\nMODELO XML CONSULTA E CANCELAMENTO:\nEMITIR NOTA SERVIÇO EXTERIOR ALÍQUOTA ZERO:\nEXPLICANDO HOMOLOGAÇÃO WEBSERVICE NFSE:",
                'links' => [
                    $link('https://www.dropbox.com/s/syr4dqsq04xb9pu/LAYOUT%20WSNFSE-RJ.pdf?dl=0', 'LAYOUT WSNFSE-RJ'),
                    $link('https://www.dropbox.com/s/g8kfomhghu4d4dk/C%C3%93DIGOS%20MUNIC%C3%8DPIOS.txt?dl=0', 'CÓDIGOS MUNICÍPIOS'),
                    $link('https://www.dropbox.com/scl/fi/zoi1h0xrnyks2xlxkgr07/modelo-xml-envio-nfse-fora-do-simples-nacional.xml?e=2&mcp_token=eyJwaWQiOjEyMzc4NDAsInNpZCI6MjEwNjM0MDE0LCJheCI6ImQxOTZmNDBjNWI3YWU5ZTllYWNhMDZjNGM1MWJjY2IwIiwidHMiOjE3MjU1NDYzNTMsImV4cCI6MTcyNzk2NTU1M30.3GpdpDtYLnIXETWs8aOKxqtPQP6kaf3gnq5gCTL0zgw&rlkey=tb8c895qetkocztgbgj12hcf7&dl=0', 'MODELO XML ENVIO NFSE'),
                    $link('https://www.dropbox.com/s/vexk3xd4ovpvfsf/Modelo%20xml%20Consulta%20e%20de%20Cancelamento.pdf?dl=0', 'MODELO XML CONSULTA E CANCELAMENTO'),
                    $link('https://www.dropbox.com/scl/fi/4r8f0bjpyek6tmrzfwt1r/Emitir-Nota-Servi-o-Exterior-Aliquota-Zero.mkv?rlkey=vmrc4qt2u4f0oxnzsb64yvg7i&dl=0', 'EMITIR NOTA SERVIÇO EXTERIOR'),
                ],
            ]),
            $node('ws_rj_2026', 'message', 1740, 820, [
                'label' => 'Webservice RJ 2026',
                'text' => 'WebService2026-RJ📇:',
                'links' => [$link('https://www.dropbox.com/scl/fi/leqk1f1x92ibk0shrm5jk/WebService2026-RJ.rar?rlkey=7yoz0tzk2jrsnt8d7bli2bllu&dl=0')],
            ]),
            $node('nfe_cancelamento', 'message', 1280, 760, [
                'label' => 'Cancelar Nota Fiscal',
                'text' => 'Cancelar Nota Fiscal:',
                'links' => [$link('https://www.dropbox.com/s/xzcm3acou2jggpi/Cancelar%20Nota%20Fiscal.mp4?dl=0')],
            ]),
            $node('nfe_logo', 'message', 1280, 980, [
                'label' => 'Inserir Logomarca',
                'text' => 'Inserir Logomarca na Nota:',
                'links' => [$link('https://www.dropbox.com/s/tv2xm0aa1n5l4b7/Inserir%20Logomarca%20na%20Nota.mp4?dl=0')],
            ]),
            $node('nfe_xml', 'message', 1280, 1200, [
                'label' => 'Baixar XML',
                'text' => 'Baixar XML das Notas Fiscais',
                'links' => [$link('https://www.dropbox.com/s/byqr18sllnljii0/Baixar%20XML%20das%20Notas%20Fiscais.mp4?dl=0')],
            ]),
            $node('nfe_boleto', 'message', 1280, 1420, [
                'label' => 'Imprimir Boleto',
                'text' => ' Emissão de Boleto📃:',
                'links' => [$link('https://www.dropbox.com/scl/fi/qvanyi13335uh2xt7f2zq/Como-Encerrar-Competencia-Prestador-e-Emitir-Boleto.mkv?rlkey=i6p21t6y3eo40oer4qqofrxh2&dl=0')],
            ]),
            $node('nfe_faturamento', 'message', 1280, 1640, [
                'label' => 'Faturamento Bruto',
                'text' => 'Faturamento Bruto Informado Errado, como corrigir📇:',
                'links' => [$link('https://www.dropbox.com/s/a3z83txebzaaj5o/Faturamento%20Bruto%20Informado%20Errado%2C%20como%20corrigir.mp4?dl=0')],
            ]),
            $node('nfe_autenticidade', 'message', 1280, 1860, [
                'label' => 'Autenticidade NFSE',
                'text' => 'Autenticidade da Nota Fiscal e Carta de Correcao📃:',
                'links' => [$link('https://www.dropbox.com/scl/fi/juwal86bviyqmpq7uclc0/Como-checar-Autenticidade-da-Nota-Fiscal-e-Carta-de-Correcao.mkv?rlkey=6zsfr40r70itkvcrcyjlk81zu&dl=0')],
            ]),

            $node('menu_vaf', 'menu', 840, 2320, [
                'label' => 'Atendimento VAF',
                'text' => '🏙️ Atendimento do VAF - Valor Adicionado Fiscal',
                'buttonLabel' => 'Selecione uma Opção',
                'sectionTitle' => 'Menu',
                'menuMode' => 'list',
                'options' => [
                    ['id' => 'baixar_manual', 'label' => 'Baixar Manual', 'description' => 'Baixar Manual do VAF - Valor Adicionado Fiscal'],
                    ['id' => 'falar_suporte', 'label' => 'Falar com o Suporte', 'description' => 'Falar com o Suporte do VAF - Valor Adicionado Fiscal'],
                    ['id' => 'voltar', 'label' => 'Voltar'],
                ],
            ]),
            $node('vaf_manual', 'message', 1280, 2200, [
                'label' => 'Manual VAF',
                'text' => 'Manual_VAF - Valor Adicionado Fiscal:',
                'links' => [$link('https://www.dropbox.com/s/g3pwokeze0yi28z/Manual_VAF%20-%20Valor%20Adicionado%20Fiscal.pdf?dl=0')],
            ]),

            $node('menu_declaracoes', 'menu', 840, 2920, [
                'label' => 'Declarações',
                'text' => '📄 Declarações ',
                'buttonLabel' => 'Selecione uma Opção',
                'sectionTitle' => 'Menu',
                'menuMode' => 'list',
                'options' => [
                    ['id' => 'tomadores', 'label' => 'Tomadores', 'description' => 'Declaração de Serviços Tomados'],
                    ['id' => 'prestadores', 'label' => 'Prestadores', 'description' => 'Declaração de Serviços Prestados'],
                    ['id' => 'voltar', 'label' => 'Voltar'],
                ],
            ]),
            $node('menu_tomados', 'menu', 1280, 2660, [
                'label' => 'Declaração de Serviços Tomados',
                'text' => '📄 Declaração de Serviços Tomados ',
                'buttonLabel' => 'Selecione uma Opção',
                'sectionTitle' => 'Título da seção',
                'menuMode' => 'list',
                'options' => [
                    ['id' => 'nao_optante', 'label' => 'Não Optante Simples', 'description' => 'Quando o Prestador NÃO é do Simples Nacional'],
                    ['id' => 'encerrar_competencia', 'label' => 'Encerrar Competência', 'description' => 'Como Encerrar Competência Tomador e Emitir Boleto'],
                    ['id' => 'optante', 'label' => 'Optante Simples', 'description' => 'Quando o Prestador É do Simples Nacional'],
                    ['id' => 'layout_cadastros', 'label' => 'Layout Cadastros', 'description' => 'Leiaute para importação de arquivo txt do cadastro de Prestadores'],
                    ['id' => 'layout_notas', 'label' => 'Layout Notas', 'description' => 'Leiaute para importação de arquivo txt das notas de Serviços Tomados'],
                    ['id' => 'importar', 'label' => 'Importar declarações', 'description' => 'Como importar arquivo de declarações de serviços tomados'],
                    ['id' => 'voltar', 'label' => 'Voltar'],
                ],
            ]),
            $node('decl_tomador', 'message', 1740, 2380, [
                'label' => 'Declaração de Tomador',
                'text' => 'Declaração de Tomador:',
                'links' => [$link('https://www.dropbox.com/s/avg0asihhjgla7w/Declara%C3%A7%C3%A3o%20de%20Tomador.mp4?dl=0')],
            ]),
            $node('decl_encerrar', 'message', 1740, 2600, [
                'label' => 'Encerrar Competência',
                'text' => 'Como Encerrar Competência Tomador e Emitir Boleto:',
                'links' => [$link('https://www.dropbox.com/scl/fi/qmqanlsgazbcgiat9l51n/Como-Encerrar-Competencia-Tomador-e-Emitir-Boleto.mkv?rlkey=jqqr4vhg25vfw85mxhi58mtfn&e=1&dl=0')],
            ]),
            $node('decl_tomador_simples', 'message', 1740, 2820, [
                'label' => 'Tomador com Prestador S.N',
                'text' => "Declaração Tomador com Prest. S.N\n:",
                'links' => [$link('https://www.dropbox.com/s/u7ry7szuzy4628j/Declara%C3%A7%C3%A3o%20Tomador%20com%20Prest.%20S.N.mp4?dl=0')],
            ]),
            $node('decl_layout_cadastros', 'message', 1740, 3040, [
                'label' => 'Layout Cadastros',
                'text' => 'layout cadastro prestadores:',
                'links' => [$link('https://www.dropbox.com/s/76fcqxzj2n904by/layout_cadastro_prestadores.txt?dl=0')],
            ]),
            $node('decl_layout_notas', 'message', 1740, 3260, [
                'label' => 'Layout Notas',
                'text' => 'layout tomador:',
                'links' => [$link('https://www.dropbox.com/s/f6fz83f1nxln2r8/layout_tomador.txt?dl=0')],
            ]),
            $node('decl_importar', 'message', 1740, 3480, [
                'label' => 'Importar Declarações',
                'text' => "Importar Declarações de Serviços Tomados:\n ",
                'links' => [$link('https://www.dropbox.com/s/42bg93ltw2xdiff/Importar%20Declara%C3%A7%C3%B5es%20de%20Servi%C3%A7os%20Tomados.mp4?dl=0')],
            ]),
            $node('menu_prestados', 'menu', 1280, 3760, [
                'label' => 'Declaração de Serviços Prestados',
                'text' => '📄 Declaração de Serviços Prestados',
                'buttonLabel' => 'Selecione uma Opção',
                'sectionTitle' => 'Menu',
                'menuMode' => 'list',
                'options' => [
                    ['id' => 'presumido_real', 'label' => 'Presumido ou Real', 'description' => 'Prestador do Lucro Presumido ou Lucro Real'],
                    ['id' => 'simples_nacional', 'label' => 'Simples Nacional', 'description' => 'Prestador do Simples Nacional'],
                    ['id' => 'voltar', 'label' => 'Voltar'],
                ],
            ]),
            $node('decl_prestador', 'message', 1740, 3700, [
                'label' => 'Declaração de Prestador',
                'text' => 'Declaração de Prestador:',
                'links' => [$link('https://www.dropbox.com/s/q2hjhu8prxp3qgf/Declara%C3%A7%C3%A3o%20de%20Prestador.mp4?dl=0')],
            ]),
            $node('decl_prestador_simples', 'message', 1740, 3920, [
                'label' => 'Prestador Simples Nacional',
                'text' => 'Declaração de Prestador Simples Nacional:',
                'links' => [$link('https://www.dropbox.com/s/vj6zwj5yt561r8q/Declara%C3%A7%C3%A3o%20de%20Prestador%20Simples%20Nacional.mp4?dl=0')],
            ]),

            $node('menu_cadastros', 'menu', 840, 4300, [
                'label' => 'Cadastros',
                'text' => '👨‍💼 Cadastros de Destinatários e Tomadores',
                'buttonLabel' => 'Selecione uma Opção',
                'sectionTitle' => 'Título da seção',
                'menuMode' => 'list',
                'options' => [
                    ['id' => 'destinatario', 'label' => 'Destinatário/Tomador', 'description' => 'Como cadastrar um destinatário/tomador'],
                    ['id' => 'estrangeiro', 'label' => 'Estrangeiro', 'description' => 'Como cadastrar um destinatário/tomador ESTRANGEIRO'],
                    ['id' => 'atualizar_tomador', 'label' => 'Atualizar Tomador', 'description' => 'Atualizar cadastro do Tomador'],
                    ['id' => 'atualizar_prestador', 'label' => 'Atualizar Prestador', 'description' => 'Atualizar cadastro do Prestador'],
                    ['id' => 'voltar', 'label' => 'Voltar'],
                ],
            ]),
            $node('cad_destinatario', 'message', 1280, 4160, [
                'label' => 'Cadastro Destinatário/Tomador',
                'text' => 'Cadastro Destinatário tomador:',
                'links' => [$link('https://www.dropbox.com/s/7dhe8nc3c3slt2q/Cadastro%20Destinatario-tomador.mp4?dl=0')],
            ]),
            $node('cad_estrangeiro', 'message', 1280, 4380, [
                'label' => 'Cadastro de Tomador Estrangeiro',
                'text' => 'Cadastro de Tomador Estrangeiro:',
                'links' => [$link('https://www.dropbox.com/s/mlsucvwmjyjb4qn/Cadastro%20de%20Tomador%20Estrangeiro.mp4?dl=0')],
            ]),

            $node('menu_senha', 'menu', 840, 4820, [
                'label' => 'Senha',
                'text' => '🔑 Senha',
                'buttonLabel' => 'Título do Botão',
                'sectionTitle' => 'Título da seção',
                'menuMode' => 'list',
                'options' => [
                    ['id' => 'bloqueada', 'label' => 'Senha Bloqueada'],
                    ['id' => 'alterar', 'label' => 'Alterar Senha'],
                    ['id' => 'voltar', 'label' => 'Voltar'],
                ],
            ]),
            $node('senha_alterar', 'message', 1280, 4840, [
                'label' => 'Alterar Senha',
                'text' => 'Alterar Senha de Acesso:',
                'links' => [$link('https://www.dropbox.com/s/8x89cn8tpdrijob/Alterar%20Senha%20de%20Acesso.mp4?dl=0')],
            ]),

            $node('suporte', 'message', 840, 5260, [
                'label' => 'Falar com o Suporte',
                'text' => 'Clique abaixo para falar com nosso atendente,              ',
                'links' => [$link('https://wa.me/5516991038606')],
            ]),
        ];

        $edge = static fn (string $id, string $source, string $target, ?string $option = null): array => array_filter([
            'id' => $id,
            'source' => $source,
            'target' => $target,
            'sourceHandle' => $option,
            'data' => $option ? ['optionKey' => $option] : null,
            'type' => 'smoothstep',
        ], static fn ($value): bool => $value !== null);

        $edges = [
            $edge('e-trigger-main', 'trigger_incoming', 'menu_main'),
            $edge('e-main-nfe', 'menu_main', 'menu_nfe', 'nfe'),
            $edge('e-main-vaf', 'menu_main', 'menu_vaf', 'vaf'),
            $edge('e-main-declaracoes', 'menu_main', 'menu_declaracoes', 'declaracoes'),
            $edge('e-main-cadastros', 'menu_main', 'menu_cadastros', 'cadastros'),
            $edge('e-main-senha', 'menu_main', 'menu_senha', 'senha'),
            $edge('e-main-suporte', 'menu_main', 'suporte', 'suporte'),

            $edge('e-nfe-emissao', 'menu_nfe', 'nfe_emissao', 'emissao'),
            $edge('e-nfe-webservice', 'menu_nfe', 'menu_webservice', 'webservice'),
            $edge('e-nfe-cancelamento', 'menu_nfe', 'nfe_cancelamento', 'cancelamento'),
            $edge('e-nfe-logo', 'menu_nfe', 'nfe_logo', 'logo'),
            $edge('e-nfe-xml', 'menu_nfe', 'nfe_xml', 'xml'),
            $edge('e-nfe-boleto', 'menu_nfe', 'nfe_boleto', 'boleto'),
            $edge('e-nfe-faturamento', 'menu_nfe', 'nfe_faturamento', 'faturamento'),
            $edge('e-nfe-autenticidade', 'menu_nfe', 'nfe_autenticidade', 'autenticidade'),
            $edge('e-nfe-voltar', 'menu_nfe', 'menu_main', 'voltar'),

            $edge('e-webservice-sp-2025', 'menu_webservice', 'ws_sp_2025', 'sp_ate_2025'),
            $edge('e-webservice-sp-2026', 'menu_webservice', 'ws_sp_2026', 'sp_desde_2026'),
            $edge('e-webservice-rj-2025', 'menu_webservice', 'ws_rj_2025', 'rj_ate_2025'),
            $edge('e-webservice-rj-2026', 'menu_webservice', 'ws_rj_2026', 'rj_desde_2026'),
            $edge('e-webservice-voltar', 'menu_webservice', 'menu_nfe', 'voltar'),
            $edge('e-ws-sp-2025-voltar', 'ws_sp_2025', 'menu_webservice'),
            $edge('e-ws-sp-2026-voltar', 'ws_sp_2026', 'menu_webservice'),
            $edge('e-ws-rj-2025-voltar', 'ws_rj_2025', 'menu_webservice'),
            $edge('e-ws-rj-2026-voltar', 'ws_rj_2026', 'menu_webservice'),
            $edge('e-cancelamento-voltar', 'nfe_cancelamento', 'menu_nfe'),
            $edge('e-logo-voltar', 'nfe_logo', 'menu_nfe'),
            $edge('e-xml-voltar', 'nfe_xml', 'menu_nfe'),
            $edge('e-boleto-voltar', 'nfe_boleto', 'menu_nfe'),
            $edge('e-faturamento-voltar', 'nfe_faturamento', 'menu_nfe'),
            $edge('e-autenticidade-voltar', 'nfe_autenticidade', 'menu_nfe'),

            $edge('e-vaf-manual', 'menu_vaf', 'vaf_manual', 'baixar_manual'),
            $edge('e-vaf-suporte', 'menu_vaf', 'suporte', 'falar_suporte'),
            $edge('e-vaf-voltar', 'menu_vaf', 'menu_main', 'voltar'),
            $edge('e-vaf-manual-voltar', 'vaf_manual', 'menu_vaf'),

            $edge('e-declaracoes-tomadores', 'menu_declaracoes', 'menu_tomados', 'tomadores'),
            $edge('e-declaracoes-prestadores', 'menu_declaracoes', 'menu_prestados', 'prestadores'),
            $edge('e-declaracoes-voltar', 'menu_declaracoes', 'menu_main', 'voltar'),
            $edge('e-tomados-nao-optante', 'menu_tomados', 'decl_tomador', 'nao_optante'),
            $edge('e-tomados-encerrar', 'menu_tomados', 'decl_encerrar', 'encerrar_competencia'),
            $edge('e-tomados-optante', 'menu_tomados', 'decl_tomador_simples', 'optante'),
            $edge('e-tomados-layout-cadastros', 'menu_tomados', 'decl_layout_cadastros', 'layout_cadastros'),
            $edge('e-tomados-layout-notas', 'menu_tomados', 'decl_layout_notas', 'layout_notas'),
            $edge('e-tomados-importar', 'menu_tomados', 'decl_importar', 'importar'),
            $edge('e-tomados-voltar', 'menu_tomados', 'menu_declaracoes', 'voltar'),
            $edge('e-tomador-voltar', 'decl_tomador', 'menu_tomados'),
            $edge('e-encerrar-voltar', 'decl_encerrar', 'menu_tomados'),
            $edge('e-tomador-simples-voltar', 'decl_tomador_simples', 'menu_tomados'),
            $edge('e-layout-cadastros-voltar', 'decl_layout_cadastros', 'menu_tomados'),
            $edge('e-layout-notas-voltar', 'decl_layout_notas', 'menu_tomados'),
            $edge('e-importar-voltar', 'decl_importar', 'menu_tomados'),
            $edge('e-prestados-real', 'menu_prestados', 'decl_prestador', 'presumido_real'),
            $edge('e-prestados-simples', 'menu_prestados', 'decl_prestador_simples', 'simples_nacional'),
            $edge('e-prestados-voltar', 'menu_prestados', 'menu_declaracoes', 'voltar'),
            $edge('e-prestador-voltar', 'decl_prestador', 'menu_prestados'),
            $edge('e-prestador-simples-voltar', 'decl_prestador_simples', 'menu_prestados'),

            $edge('e-cadastros-destinatario', 'menu_cadastros', 'cad_destinatario', 'destinatario'),
            $edge('e-cadastros-estrangeiro', 'menu_cadastros', 'cad_estrangeiro', 'estrangeiro'),
            $edge('e-cadastros-atualizar-tomador', 'menu_cadastros', 'suporte', 'atualizar_tomador'),
            $edge('e-cadastros-atualizar-prestador', 'menu_cadastros', 'suporte', 'atualizar_prestador'),
            $edge('e-cadastros-voltar', 'menu_cadastros', 'menu_main', 'voltar'),
            $edge('e-destinatario-voltar', 'cad_destinatario', 'menu_cadastros'),
            $edge('e-estrangeiro-voltar', 'cad_estrangeiro', 'menu_cadastros'),

            $edge('e-senha-bloqueada', 'menu_senha', 'suporte', 'bloqueada'),
            $edge('e-senha-alterar', 'menu_senha', 'senha_alterar', 'alterar'),
            $edge('e-senha-voltar', 'menu_senha', 'menu_main', 'voltar'),
            $edge('e-alterar-senha-voltar', 'senha_alterar', 'menu_senha'),

            $edge('e-suporte-voltar', 'suporte', 'menu_main'),
        ];

        $ouvidoria = app(OuvidoriaFlowFactory::class)->branch('menu_main', 'ouvidoria');
        $nodes = array_merge($nodes, $ouvidoria['nodes']);
        $edges = array_merge($edges, $ouvidoria['edges']);

        $esic = app(EsicFlowFactory::class)->branch('menu_main', 'esic');
        $nodes = array_merge($nodes, $esic['nodes']);
        $edges = array_merge($edges, $esic['edges']);

        $acompanhamento = app(AcompanhamentoFlowFactory::class)->branch('menu_main', 'acompanhamento');
        $nodes = array_merge($nodes, $acompanhamento['nodes']);
        $edges = array_merge($edges, $acompanhamento['edges']);

        $returningMessages = [];
        foreach ($edges as $flowEdge) {
            if (empty($flowEdge['sourceHandle'])) {
                $returningMessages[$flowEdge['source']] = true;
            }
        }

        foreach ($nodes as &$flowNode) {
            if ($flowNode['type'] === 'message' && isset($returningMessages[$flowNode['id']])) {
                $flowNode['data']['continueLabel'] ??= 'Voltar';
            }
        }
        unset($flowNode);

        return [
            'schemaVersion' => 2,
            'source' => [
                'provider' => 'manychat',
                'name' => 'Newchatbot',
                'share_hash' => '1237840_b49573d180a7192297c610af5654ccc2fb587f60',
                'imported_at' => '2026-07-28',
            ],
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 0.7],
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
