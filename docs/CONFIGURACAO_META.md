# Configuração do WhatsApp na Meta

## Pré-requisitos

- portfólio empresarial da empresa;
- aplicativo Meta do tipo Empresa;
- WhatsApp Business Account (WABA);
- número que possa receber o código de verificação;
- empresa verificada para sair do ambiente de teste;
- domínio público com HTTPS válido.

`maissflow.test` funciona apenas na máquina local. A Meta não consegue
chamar esse endereço. Para homologação, use um subdomínio HTTPS controlado pela
empresa ou um túnel temporário restrito; para produção, use endpoint estável,
monitorado e protegido.

## 1. Concluir a configuração básica

No aplicativo já criado, escolha **Integrar com API** e conclua:

1. experimento com o número de teste;
2. vinculação da WABA e do número de produção;
3. verificação da empresa;
4. criação de usuário de sistema no Gerenciador de Negócios;
5. geração do token permanente com as permissões necessárias ao WhatsApp;
6. associação correta do aplicativo, WABA e número ao usuário de sistema.

Não coloque token permanente em repositório, captura de tela, ticket ou conversa.

## 2. Preencher o canal no MaissFlow

Abra **Configurações → WhatsApp Cloud API** e informe:

- **Phone Number ID**: identificador técnico do número;
- **WABA ID**: identificador da conta do WhatsApp Business;
- **versão do Graph**: versão habilitada no aplicativo;
- **Access Token**: token do usuário de sistema;
- **App Secret**: segredo do aplicativo;
- **Verify Token**: texto aleatório, exclusivo e com pelo menos 24 caracteres.

Campos secretos vazios preservam o valor existente. A API só informa se o
segredo está presente; nunca o devolve ao navegador.

Use **Testar conexão**. O teste consulta o número diretamente na Graph API e só
marca o canal como conectado quando a Meta responde com sucesso.

## 3. Configurar o webhook

Callback:

```text
https://SEU-DOMINIO/api/webhooks/whatsapp
```

Verify token: exatamente o mesmo valor salvo no MaissFlow.

Ao validar, a Meta fará:

```text
GET /api/webhooks/whatsapp
  ?hub.mode=subscribe
  &hub.verify_token=...
  &hub.challenge=...
```

Depois, assine ao menos o campo `messages` da WABA. Mensagens recebidas e
atualizações de entrega usam o mesmo endpoint `POST`.

O `App Secret` é obrigatório no servidor porque o MaissFlow rejeita qualquer
POST sem assinatura `X-Hub-Signature-256` válida.

## 4. Preparar o ambiente de aplicação

Configure MySQL e a fila persistente do Laravel. No deploy:

```powershell
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Mantenha um worker dedicado:

```powershell
php artisan queue:work database --queue=default --tries=5 --backoff=5
```

Cadastre esse comando no gerenciador Laravel do Plesk e mantenha
`WEBHOOK_QUEUE=default`. Garanta que o balanceador preserve o corpo bruto da
requisição. A assinatura é
calculada sobre os bytes exatos recebidos; reformatar JSON antes do Laravel
invalida a verificação.

## 5. Teste controlado

1. publique a automação no MaissFlow;
2. envie uma mensagem a partir de um telefone autorizado;
3. confirme a criação do contato e da conversa;
4. percorra cada opção do menu;
5. valide o retorno ao menu anterior;
6. acione **Suporte** e confirme a transferência para humano;
7. responda pela caixa de entrada dentro da janela de 24 horas;
8. confira os status entregue/lido/falha e os logs do worker;
9. reenvie intencionalmente o mesmo webhook e confirme que não duplicou;
10. teste indisponibilidade temporária da Meta e o retry da fila.

## 6. Regras operacionais

- fora da janela de 24 horas, inicie a conversa somente com template aprovado;
- mantenha a versão da Graph API explícita e revise antes da descontinuação;
- aplique opt-in, opt-out e retenção conforme LGPD e políticas do WhatsApp;
- limite acesso ao Gerenciador de Negócios e faça rotação periódica dos tokens;
- monitore erros por código, atraso da fila e taxa de mensagens não entregues;
- nunca reutilize o número simultaneamente em provedores incompatíveis.
