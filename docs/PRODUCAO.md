# Produção: critérios de liberação e roadmap

## Estado atual

### Implementado e validável localmente

- domínio Laravel e banco relacional;
- editor visual e edição completa dos menus;
- autosave protegido contra concorrência;
- validação, publicação imutável e simulador;
- runtime de entrada, menus e transferência humana;
- inbox, contatos, respostas manuais e janela de atendimento;
- webhook assinado, idempotência, fila, auditoria e segredos criptografados;
- testes automatizados, build de produção e interface no Laragon.

### Depende do ambiente da empresa

- credenciais reais do aplicativo Meta;
- número de produção e WABA aprovados;
- domínio público HTTPS para o callback;
- usuário e banco MySQL de produção;
- worker supervisionado;
- substituição dos links demonstrativos `suporte.exemplo.com.br`;
- política de backup, retenção, alertas e rotação dos segredos;
- teste completo com mensagens e cobrança reais.

Não marque a integração como pronta para produção antes dessas validações.

## Checklist de go-live

- [ ] Empresa, WABA, número e nome de exibição aprovados na Meta.
- [ ] Token permanente pertence a usuário de sistema com privilégio mínimo.
- [ ] `APP_ENV=production`, `APP_DEBUG=false` e `APP_KEY` protegida.
- [ ] MySQL com usuário exclusivo, backup e restauração ensaiada.
- [ ] HTTPS válido e webhook acessível externamente.
- [ ] Assinatura inválida retorna erro e não persiste evento.
- [ ] Worker da fila reinicia automaticamente.
- [ ] `php artisan migrate --force` executado com backup e rollback previstos.
- [ ] Cache de configuração/rotas/views reconstruído.
- [ ] Automação publicada e todos os caminhos revisados por atendimento.
- [ ] URLs e textos demonstrativos substituídos por conteúdo oficial.
- [ ] Testes reais de entrada, opção, handoff, resposta, entrega e falha executados.
- [ ] Alertas para fila parada, webhook com erro e falha da Meta configurados.
- [ ] Retenção, base legal, direitos do titular e perfis de acesso definidos.
- [ ] Senha do usuário de demonstração alterada ou usuário removido.

## Roadmap recomendado

### P0 — antes de produção

- provisionar MySQL, domínio HTTPS e worker no gerenciador Laravel do Plesk;
- cadastrar credenciais e validar o webhook real;
- revisar cada texto, URL e regra do fluxo com a equipe;
- adicionar política de templates para mensagens fora da janela;
- integrar logs estruturados e alertas.

### P1 — operação assistida

- gestão de templates aprovados;
- anexos e mídia;
- respostas rápidas e notas internas;
- atribuição por equipe/agente e SLA;
- busca e filtros avançados com paginação por cursor;
- painel de falhas, reprocessamento seguro e métricas por nó.

### P2 — automação avançada

- ações externas por conectores explicitamente autorizados;
- condições sobre campos de contato;
- atrasos persistentes e agendamento;
- variáveis tipadas e campos personalizados;
- importação assistida do fluxo legado;
- ambiente de homologação e promoção entre versões.

### Fora do escopo inicial

Campanhas em massa, cobrança, IA generativa, omnichannel e marketplace de
integrações não são necessários para substituir o fluxo mostrado. Cada item deve
ser tratado como projeto separado por envolver políticas, custos e riscos
operacionais próprios.

## Estratégia de rollback

1. não apague a versão publicada anterior;
2. antes do deploy, faça backup do banco e registre a versão do código;
3. migrations destrutivas devem ser separadas e somente executadas após janela
   de compatibilidade;
4. se a nova versão falhar, republique a automação anterior e restaure o código;
5. reinicie os workers para eliminar código antigo em memória;
6. execute novamente webhook, inbox e um caminho completo após o rollback.
