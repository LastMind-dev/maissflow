# Relatório da integração MaissFlow com WhatsApp Cloud API

**Data de referência:** 25/09/2026 (America/Sao_Paulo)

**Projeto:** MaissFlow — `https://maissflow.fgmaiss.com.br`

**Escopo:** configuração do aplicativo Meta, webhook, credenciais protegidas, registro do número da Ouvidoria e publicação das alterações do projeto.

Este documento registra as evidências obtidas até a data acima. Um teste de consulta à Graph API, a validação do webhook e a publicação do código são etapas distintas de um teste real de conversa. Nenhuma credencial, PIN ou segredo está reproduzido aqui.

## 1. Identificadores confirmados

| Item | Valor |
| --- | --- |
| Portfólio empresarial | BotMaiss (`1334964161772365`) |
| Aplicativo Meta escolhido | Ouvidoria Municipal (`1423439529669128`) |
| Conta do WhatsApp Business da Ouvidoria | Ouvidoria Municipal de Itapagipe MG (`2877390999306105`) |
| Número da Ouvidoria | `+55 34 92000-6744` |
| Phone Number ID da Ouvidoria | `1352183974645624` |
| Callback do webhook | `https://maissflow.fgmaiss.com.br/api/webhooks/whatsapp` |
| Política de Privacidade | `https://maissflow.fgmaiss.com.br/politica-de-privacidade` |

Também existe a conta **MaissDoc** (`2559727274468935`), com o número `+55 17 99618-0202` e Phone Number ID `1240060715852070`. Ela é um ativo diferente e não deve substituir a conta ou o número da Ouvidoria nas configurações do MaissFlow.

## 2. Conclusões técnicas

1. **O motivo confirmado para o número não aparecer para conversar é o registro pendente.** A captura mais recente do Gerenciador do WhatsApp mostra o telefone da Ouvidoria como **Pendente** e apresenta a instrução de registrá-lo pela API de registro. O nome de exibição permanece **Em análise**. No aplicativo WhatsApp do usuário, a tentativa de conversar ofereceu apenas convite por SMS.
2. **A conta empresarial e a WABA não são o mesmo estado do telefone.** A conta do WhatsApp Business da Ouvidoria foi observada como **Aprovada**, mas seu telefone continuou pendente. A aprovação da conta, por si só, não registra o Phone Number ID para a Cloud API.
3. **“Testar conexão” verifica a consulta do número, não a capacidade de conversar.** O MaissFlow conseguiu consultar o Phone Number ID na Graph API e marcou a conexão como válida. Essa resposta não demonstra registro do telefone, envio ou recebimento de mensagens.
4. **O webhook está configurado, mas falta a prova de ponta a ponta.** A Meta aceitou e salvou a URL de callback e o token de verificação; o campo `messages` foi assinado. O webhook de amostra da Meta usou um Phone Number ID fictício, por isso o MaissFlow respondeu `202 EVENT_IGNORED` para esse evento. Esse resultado confirma o tratamento seguro de um evento desconhecido, mas não uma conversa real.
5. **Há uma vinculação do app que ainda precisa ser conferida.** Na última inspeção da tela “Configuração da API” do app *Ouvidoria Municipal*, o seletor de remetente exibia apenas o número do MaissDoc. A aba “Ativos conectados” do app em Configurações da Empresa mostrava “Nenhum ativo conectado”. Essas observações exigem revisão da associação e das permissões do app, da WABA da Ouvidoria e do usuário de sistema após o registro do telefone. Não se concluiu que esse seja o único bloqueio.
6. **Os segredos têm funções diferentes.** O token permanente do usuário de sistema autoriza chamadas à Graph API; o App Secret valida a assinatura HMAC dos POSTs recebidos; o token de verificação atende ao desafio inicial do webhook; o PIN de seis dígitos registra o telefone e ativa a verificação em duas etapas. O PIN não é o token do webhook.

Segundo a [documentação da Meta para registro de telefones](https://www.postman.com/meta/whatsapp-business-platform/folder/zuoeksl/registration), a chamada `POST /{Phone-Number-ID}/register` requer um token de usuário de sistema com `whatsapp_business_messaging`, `messaging_product: whatsapp` e um PIN de seis dígitos. A propriedade do número também precisa estar verificada por SMS ou voz antes do registro.

## 3. Realizações confirmadas

### Meta e aplicação

- O canal do MaissFlow recebeu os identificadores da WABA e do telefone da **Ouvidoria**, além dos segredos necessários, armazenados em campos criptografados. Os segredos salvos não são devolvidos ao navegador.
- A URL pública do webhook foi validada e salva no app Meta. O campo `messages` foi assinado; não foram habilitados indiscriminadamente outros eventos sem necessidade funcional demonstrada.
- O app *Ouvidoria Municipal* foi observado publicado: a tela passou a oferecer a ação **Tirar do ar**.
- A Política de Privacidade pública do MaissFlow foi criada, testada e cadastrada na Meta. Uma URL de Termos de Serviço sem página correspondente foi removida, sem criar uma declaração legal fictícia.
- No Plesk, foram observadas tarefas agendadas e fila habilitadas, sem jobs falhados naquele momento. O `.env` de produção foi ajustado para `APP_ENV=production`, `APP_DEBUG=false` e URL HTTPS correta, com permissão restrita no arquivo. Isso foi verificado em 24/09; a execução contínua do worker ainda deve ser comprovada com mensagem real.

### Código, segurança e entrega

- Foi criada a ação **Configurações → Canal WhatsApp → Registrar número na Meta**. Ela usa o token já protegido no servidor e envia o PIN informado pelo proprietário do workspace somente à API de registro da Meta.
- O endpoint exige autenticação e papel `owner`, valida PIN de exatamente seis dígitos, limita tentativas a três por dez minutos e registra na auditoria apenas resultado, status HTTP e códigos de erro. O PIN não é salvo no canal, na auditoria ou na resposta; também foi excluído dos dados antigos de sessão em erros de validação.
- Os testes automatizados passaram: **33 testes e 175 asserções** na suíte completa e **4 testes e 20 asserções** no teste específico. O build Vite e a verificação de formatação PHP passaram.
- A auditoria de dependências JavaScript de produção retornou **zero vulnerabilidades**. O lockfile Composer foi atualizado pontualmente de `league/commonmark` `2.8.3` para `2.10.3` e de `nette/schema` `1.3.5` para `1.3.6`; depois disso, a auditoria do lockfile não apontou avisos. A instalação dessas versões no fornecedor de produção não foi verificada nesta sessão.
- Código e bundle foram enviados ao branch `main`. O servidor voltou a responder `200` após uma indisponibilidade `503` transitória durante o deploy. A rota nova foi observada em produção por um `GET` seguro que retornou `405` (rota existente, método não permitido). O manifesto de produção passou a apontar para o novo arquivo JavaScript, cuja resposta HTTP foi `200`.

| Commit | Entrega |
| --- | --- |
| `ed38365` | Versão inicial da aplicação MaissFlow |
| `1c5702d` | Política de Privacidade pública |
| `425e9fa` | Registro administrativo e seguro do número pela API Meta |
| `1729a01` | Atualização pontual do parser Markdown no lockfile |
| `48ccd6d` | Bundle Vite e manifesto necessários à interface em produção |

## 4. Pendências e critérios de conclusão

1. **Ação do proprietário:** entrar no MaissFlow, abrir **Configurações → Canal WhatsApp**, confirmar que o Phone Number ID exibido é `1352183974645624`, criar ou informar o PIN de seis dígitos e clicar em **Registrar este número**. O PIN deve permanecer com o proprietário; não deve ser enviado em conversa ou ticket.
2. **Confirmar a resposta da Meta:** a operação só estará concluída quando a API retornar sucesso e o Gerenciador do WhatsApp deixar de apresentar o telefone como pendente de registro. Caso haja erro, registrar somente os códigos de erro e verificar propriedade do número, permissão do token e associação à WABA correta, sem divulgar o PIN ou o token.
3. **Rever app e remetente:** confirmar que a WABA e o número da Ouvidoria passam a aparecer no app *Ouvidoria Municipal* como remetente, sem alterar a WABA do MaissDoc por engano.
4. **Executar prova real de operação:** enviar mensagem de um celular para `+55 34 92000-6744`, confirmar recebimento do webhook assinado, processamento pelo worker, criação da conversa no MaissFlow, resposta pelo fluxo ou inbox e entrega de uma mensagem de volta. Conferir também o estado do nome de exibição e os erros da Meta.
5. **Validar o runtime publicado:** o código, a rota e os ativos HTTP foram confirmados; a interface autenticada, a instalação Composer no Plesk e o registro real não foram executados nem comprovados nesta sessão.

**Estado final em 25/09/2026:** a aplicação está preparada para solicitar o registro do telefone com o PIN informado diretamente pelo proprietário. A integração do número da Ouvidoria **ainda não pode ser declarada operacional** até a Meta confirmar o registro e uma conversa real passar por todas as etapas acima.
